<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi H2H BTN Syariah via bridge Go (indonusa_h2h_v2).
 *
 * Alur:
 * 1. terbitkanBilling(): RAG -> POST /sikeu/billing (header sopingi-sikeu).
 *    CUSTID (nomor_pembayaran) = no_pendaftaran (calon) atau NIM (mahasiswa).
 * 2. Mahasiswa membayar di kanal BTN dengan CUSTID tersebut (inquiry/payment
 *    ditangani bridge, tabel va_billings).
 * 3. sinkronTerbayar(): polling va_billings yang sudah ada tanggal_transaksi
 *    via koneksi mysql_h2h, lalu terapkan ke tagihan RAG (lunas/sebagian) +
 *    saldo unit kas BSN + jurnal Dr Kas kanal / Cr Piutang.
 */
class BsnH2hService
{
    public const GATEWAY_NAME = 'bsn_h2h';

    /**
     * Biaya layanan VA BTN Syariah per billing (dibebankan ke pembayar di bridge,
     * tidak menambah tagihan RAG — sinkron tetap mengalokasi maksimal sisa tagihan).
     */
    public const VA_FEE = 1500;

    /**
     * Baris konfigurasi dari menu Payment Gateway Bank (gateway_name = bsn_h2h).
     */
    public static function config(): ?PaymentGatewayConfig
    {
        try {
            return PaymentGatewayConfig::where('gateway_name', self::GATEWAY_NAME)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Dari mana konfigurasi dibaca: 'menu' (DB) atau 'env' (fallback .env).
     */
    public static function configSource(): string
    {
        return self::config() ? 'menu' : 'env';
    }

    /**
     * H2H dianggap aktif jika: belum ada baris menu (mode env lama),
     * atau baris menu ada dan is_active = true.
     */
    public static function isEnabled(): bool
    {
        $cfg = self::config();

        return $cfg ? (bool) $cfg->is_active : true;
    }

    public static function bridgeUrl(): string
    {
        $cfg = self::config();
        if ($cfg && !empty($cfg->base_url)) {
            return rtrim($cfg->base_url, '/');
        }

        return rtrim(env('H2H_BRIDGE_URL', 'http://localhost:3002'), '/');
    }

    public static function bridgeToken(): string
    {
        $cfg = self::config();
        if ($cfg && !empty($cfg->api_key_encrypted)) {
            return (string) $cfg->api_key_encrypted;
        }

        return (string) env('H2H_BRIDGE_TOKEN', '');
    }

    /**
     * Terapkan kredensial DB bridge dari menu ke koneksi runtime 'mysql_h2h'.
     * Dipanggil sebelum setiap akses DB bridge agar setting menu langsung berlaku
     * tanpa restart / tanpa .env.
     */
    public static function applyDbConfig(): void
    {
        $cfg = self::config();

        $conn = [
            'driver' => 'mysql',
            'host' => $cfg?->db_host ?: env('H2H_DB_HOST', '127.0.0.1'),
            'port' => $cfg?->db_port ?: env('H2H_DB_PORT', '3306'),
            'database' => $cfg?->db_name ?: env('H2H_DB_DATABASE', 'sikeudb'),
            'username' => $cfg?->db_username ?: env('H2H_DB_USERNAME', 'root'),
            'password' => ($cfg && $cfg->db_password_encrypted !== null && $cfg->db_password_encrypted !== '')
                ? $cfg->db_password_encrypted
                : env('H2H_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];

        config()->set('database.connections.mysql_h2h', $conn);
        DB::purge('mysql_h2h');
    }

    /**
     * CUSTID untuk bank: no_pendaftaran (calon SPMB) atau NIM (mahasiswa aktif).
     */
    public static function custIdUntukTagihan(TagihanMahasiswa $tagihan): array
    {
        $tagihan->loadMissing(['calonMahasiswa', 'mahasiswa', 'tipeTagihanMahasiswa']);

        if ($tagihan->calon_mahasiswa_id && $tagihan->calonMahasiswa) {
            $noPend = $tagihan->calonMahasiswa->no_pendaftaran;
            $nama = $tagihan->calonMahasiswa->nama_lengkap;
            $nim = $tagihan->calonMahasiswa->no_pendaftaran;
        } else {
            $noPend = $tagihan->mahasiswa?->nim
                ?? $tagihan->tipeTagihanMahasiswa?->nim
                ?? (string) $tagihan->mahasiswa_id;
            $nama = $tagihan->mahasiswa?->nama_lengkap
                ?? $tagihan->tipeTagihanMahasiswa?->nama_mahasiswa
                ?? ('Mahasiswa #' . $tagihan->mahasiswa_id);
            $nim = $tagihan->mahasiswa?->nim ?? $tagihan->tipeTagihanMahasiswa?->nim ?? '';
        }

        $prodi = $tagihan->mahasiswa?->programStudi?->nama ?? '-';
        $fakultas = '-';

        return [
            'custid' => (string) $noPend,
            'nim' => (string) $nim,
            'nama' => (string) $nama,
            'fakultas' => (string) $fakultas,
            'prodi' => (string) $prodi,
        ];
    }

    /**
     * Terbitkan billing ke bridge. Mengembalikan [id_billing, id_tagihan].
     *
     * Idempoten: tagihan yang sudah punya h2h_billing_id dan belum lunas
     * tidak diterbitkan ulang (mencegah billing ganda di bridge), kecuali
     * $force = true (mis. billing di bridge kedaluwarsa/terhapus).
     */
    public static function terbitkanBilling(TagihanMahasiswa $tagihan, bool $force = false): array
    {
        if (!self::isEnabled()) {
            throw new \RuntimeException('Kanal H2H BTN Syariah belum diaktifkan di menu Payment Gateway Bank.');
        }

        $tagihan->loadMissing(['details.masterBiaya']);

        $identitas = self::custIdUntukTagihan($tagihan);

        $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
        $sisa = max(0, $totalBersih - (float) $tagihan->total_bayar);
        if ($sisa <= 0) {
            throw new \InvalidArgumentException('Tagihan sudah lunas, tidak perlu billing H2H.');
        }

        if (!$force && !empty($tagihan->h2h_billing_id)) {
            return [
                'id_billing' => $tagihan->h2h_billing_id,
                'id_tagihan' => $tagihan->h2h_id_tagihan,
                'custid' => $tagihan->h2h_custid ?: $identitas['custid'],
                'nominal' => $sisa,
                'va_fee' => self::VA_FEE,
                'total_bridge' => $sisa + self::VA_FEE,
                'reused' => true,
            ];
        }

        $details = [];
        foreach ($tagihan->details as $d) {
            $nominalBersih = (float) ($d->nominal_bersih ?? $d->nominal ?? 0);
            if ($nominalBersih <= 0) {
                continue;
            }
            $details[] = [
                'jenis_biaya' => substr((string) ($d->keterangan ?? 'UKT'), 0, 20),
                'id_tagihan' => (string) $d->id,
                'nama_biaya' => substr((string) ($d->masterBiaya->nama ?? $d->keterangan ?? 'Biaya Kuliah'), 0, 255),
                'tagihan' => (float) ($d->nominal ?? 0),
                'potongan' => (float) ($d->potongan ?? 0),
                'jumlah' => $nominalBersih,
                'kekurangan' => $nominalBersih,
                'keterangan' => substr((string) ($tagihan->nomor_tagihan ?? ''), 0, 30),
            ];
        }
        if (empty($details)) {
            $details[] = [
                'jenis_biaya' => 'UKT',
                'id_tagihan' => (string) $tagihan->id,
                'nama_biaya' => substr('Tagihan ' . ($tagihan->nomor_tagihan ?? ''), 0, 255),
                'tagihan' => $sisa,
                'potongan' => 0,
                'jumlah' => $sisa,
                'kekurangan' => $sisa,
                'keterangan' => substr((string) ($tagihan->nomor_tagihan ?? ''), 0, 30),
            ];
        }

        // Biaya layanan VA dibayar pembayar di kanal bank (di luar tagihan RAG).
        $fee = self::VA_FEE;
        $details[] = [
            'jenis_biaya' => 'BIAYA LAYANAN',
            'id_tagihan' => 'FEE-' . (string) $tagihan->id,
            'nama_biaya' => 'Biaya layanan VA BTN Syariah',
            'tagihan' => $fee,
            'potongan' => 0,
            'jumlah' => $fee,
            'kekurangan' => $fee,
            'keterangan' => substr((string) ($tagihan->nomor_tagihan ?? ''), 0, 30),
        ];
        $totalBridge = $sisa + $fee;

        $payload = [
            'nomor_pembayaran' => $identitas['custid'],
            'no_pend' => $identitas['custid'],
            'nim' => $identitas['nim'],
            'nama' => $identitas['nama'],
            'fakultas' => $identitas['fakultas'],
            'prodi' => $identitas['prodi'],
            'total_nominal' => $totalBridge,
            'api_bank' => 'BTN Syariah',
            'billing_detail' => $details,
        ];

        $res = Http::timeout(20)
            ->withHeaders(['sopingi-sikeu' => self::bridgeToken()])
            ->post(self::bridgeUrl() . '/sikeu/billing', $payload);

        if (!$res->successful()) {
            throw new \RuntimeException('Bridge H2H menolak billing (HTTP ' . $res->status() . '): ' . $res->body());
        }

        $body = $res->json();
        if (($body['rc'] ?? '') !== 'OK') {
            throw new \RuntimeException('Bridge H2H: ' . ($body['pesan'] ?? 'gagal membuat billing'));
        }

        $data = $body['data'] ?? [];
        $tagihan->update([
            'h2h_billing_id' => $data['id'] ?? null,
            'h2h_id_tagihan' => $data['id_tagihan'] ?? null,
            'h2h_custid' => $identitas['custid'],
        ]);

        AuditLogServiceSafe::record('SIKEU', 'h2h_billing', 'sikeu_tagihan_mahasiswa', $tagihan->id, [
            'h2h_billing_id' => $data['id'] ?? null,
            'custid' => $identitas['custid'],
            'nominal' => $sisa,
            'va_fee' => $fee,
        ]);

        return [
            'id_billing' => $data['id'] ?? null,
            'id_tagihan' => $data['id_tagihan'] ?? null,
            'custid' => $identitas['custid'],
            'nominal' => $sisa,
            'va_fee' => $fee,
            'total_bridge' => $totalBridge,
            'reused' => false,
        ];
    }

    /**
     * Kode transaksi pengganti yang deterministik untuk baris bridge tanpa
     * id_transaksi: stabil lintas hari (pakai tanggal_transaksi bank),
     * sehingga polling ulang tidak mencatat pembayaran ganda.
     */
    public static function fallbackKode(object $row): string
    {
        $tgl = date('Ymd', strtotime((string) ($row->tanggal_transaksi ?? 'now')));

        return 'TRX-H2H-' . $tgl . '-' . $row->id;
    }

    protected static function sudahDiproses(object $row): bool
    {
        $kode = !empty($row->id_transaksi) ? (string) $row->id_transaksi : self::fallbackKode($row);

        return Pembayaran::where('kode_transaksi', $kode)->exists();
    }

    /**
     * Polling va_billings terbayar yang belum masuk RAG.
     * Mengembalikan ringkasan [diproses, dilewati, gagal].
     */
    public static function sinkronTerbayar(int $limit = 100): array
    {
        $hasil = ['diproses' => 0, 'dilewati' => 0, 'gagal' => 0, 'detail' => []];

        self::applyDbConfig();

        try {
            $rows = DB::connection('mysql_h2h')->table('va_billings')
                ->whereNotNull('tanggal_transaksi')
                ->whereNull('deleted_at')
                ->orderBy('tanggal_transaksi', 'asc')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::error('H2H sync: koneksi mysql_h2h gagal: ' . $e->getMessage());
            $hasil['error'] = 'Koneksi database bridge H2H gagal: ' . $e->getMessage();
            return $hasil;
        }

        foreach ($rows as $row) {
            // Idempotensi: pembayaran untuk billing ini sudah masuk RAG.
            if (self::sudahDiproses($row)) {
                $hasil['dilewati']++;
                continue;
            }

            $tagihan = TagihanMahasiswa::where('h2h_billing_id', $row->id)->first();
            if (!$tagihan) {
                $hasil['dilewati']++;
                $hasil['detail'][] = "Billing {$row->id} tidak terpetakan ke tagihan RAG.";
                continue;
            }

            // Tagihan sudah lunas via kanal lain (kasir/manual): pembayaran bank
            // yang telat datang tidak boleh dihitung gagal setiap polling.
            $sisaTagihan = max(0, (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - (float) $tagihan->total_bayar);
            if ($sisaTagihan <= 0) {
                $hasil['dilewati']++;
                $hasil['detail'][] = "Billing {$row->id}: tagihan {$tagihan->nomor_tagihan} sudah lunas di RAG, perlu rekonsiliasi manual.";
                continue;
            }

            try {
                self::terapkanPembayaran($tagihan, $row);
                $hasil['diproses']++;
            } catch (\Throwable $e) {
                $hasil['gagal']++;
                $hasil['detail'][] = "Billing {$row->id}: " . $e->getMessage();
                Log::error('H2H sync gagal ' . $row->id . ': ' . $e->getMessage());
            }
        }

        return $hasil;
    }

    protected static function terapkanPembayaran(TagihanMahasiswa $tagihan, object $row): void
    {
        DB::transaction(function () use ($tagihan, $row) {
            $nominal = (float) $row->total_nominal;

            $totalBersih = (float) ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
            $sisa = max(0, $totalBersih - (float) $tagihan->total_bayar);
            $alokasi = min($nominal, $sisa);
            if ($alokasi <= 0) {
                throw new \RuntimeException("Tagihan {$tagihan->nomor_tagihan} sudah lunas di RAG.");
            }

            $unitKas = JurnalSikeuService::resolveUnitKasUntukChannel('VA_BSN', 'BSN');

            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'unit_kas_id' => $unitKas?->id,
                'kode_transaksi' => !empty($row->id_transaksi) ? (string) $row->id_transaksi : self::fallbackKode($row),
                'jumlah_bayar' => $alokasi,
                'waktu_bayar' => $row->tanggal_transaksi,
                'channel_bayar' => 'VA_BSN',
                'bank_pengirim' => 'BSN',
                'status' => 'success',
                'diverifikasi_oleh' => 1,
                'catatan' => 'Pelunasan H2H BTN Syariah (billing ' . $row->id . ', jurnal bank ' . ($row->nomor_jurnal_pembukuan ?? '-') . ')',
            ]);

            if ($unitKas) {
                $unitKas->increment('saldo_saat_ini', $alokasi);
            }

            $newTotalBayar = (float) $tagihan->total_bayar + $alokasi;
            $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');
            $tagihan->update(['total_bayar' => $newTotalBayar, 'status' => $newStatus]);
            \App\Models\Sikeu\DetailTagihan::alokasikan($tagihan, $alokasi);

            AutoJournalService::recordStudentPaymentJournal($tagihan, $alokasi, $unitKas);

            AuditLogServiceSafe::record('SIKEU', 'h2h_sync', 'sikeu_pembayaran', $pembayaran->id, [
                'h2h_billing_id' => $row->id,
                'nominal' => $alokasi,
                'tagihan_status' => $newStatus,
            ]);
        });
    }
}

/**
 * Pembungkus AuditLog agar sync CLI (tanpa request) tidak fatal
 * saat auth()/request tidak tersedia.
 */
class AuditLogServiceSafe
{
    public static function record(string $module, string $action, string $table, int $recordId, ?array $newValues = null): void
    {
        try {
            \App\Services\AuditLogService::record(
                module: $module,
                action: $action,
                tableName: $table,
                recordId: $recordId,
                newValues: $newValues,
                request: request(),
            );
        } catch (\Throwable $e) {
            Log::warning('AuditLog H2H dilewati: ' . $e->getMessage());
        }
    }
}
