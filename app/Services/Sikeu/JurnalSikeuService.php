<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Support\Str;

/**
 * Jurnal otomatis SIKEU dengan basis akrual:
 * - Terbit tagihan : Dr Piutang (103.01) / Cr Pendapatan (401.01 UKT / 401.02 SPMB)
 * - Bayar           : Dr Kas/Bank / Cr Piutang (103.01)
 * - Koreksi bayar   : Dr Piutang / Cr Kas
 * - Hapus tagihan   : Dr Pendapatan / Cr Piutang (pembalik penerbitan)
 * - Potongan        : Dr Beban Beasiswa & Potongan (504.01) / Cr Piutang (+ sebaliknya saat batal)
 * - Dispensasi ACC  : jurnal memorandum bernilai nol (tanpa efek saldo, hanya jejak)
 *
 * Semua method berasumsi dipanggil di dalam transaksi database milik controller.
 */
class JurnalSikeuService
{
    /**
     * Prefix nomor jurnal per jenis, dapat dikonfigurasi kampus via
     * menu Pengaturan (SystemSetting `sikeu.jurnal_prefix_*`).
     */
    public const PREFIX_DEFAULTS = [
        'pembayaran' => 'JRN-PAY',
        'penerbitan' => 'JRN-TAG',
        'koreksi' => 'JRN-REV',
        'pembatalan_tagihan' => 'JRN-BTL',
        'potongan' => 'JRN-POT',
        'pembatalan_potongan' => 'JRN-BPT',
        'dispensasi_memo' => 'JRN-DSP',
        'penutupan' => 'JRN-TUTUP',
        'pemasukan' => 'JRN-INC',
        'pengeluaran' => 'JRN-EXP',
        'manual' => 'JRN-',
    ];

    public static function prefix(string $jenis): string
    {
        $stored = \App\Models\SystemSetting::get('sikeu.jurnal_prefix_' . $jenis, self::PREFIX_DEFAULTS[$jenis] ?? 'JRN-');
        $stored = strtoupper(trim((string) $stored));

        if (!preg_match('/^[A-Z0-9-]{1,12}$/', $stored)) {
            return self::PREFIX_DEFAULTS[$jenis] ?? 'JRN-';
        }

        return $stored;
    }

    public static function daftarPrefix(): array
    {
        $out = [];
        foreach (self::PREFIX_DEFAULTS as $jenis => $default) {
            $out[$jenis] = [
                'default' => $default,
                'nilai' => self::prefix($jenis),
            ];
        }

        return $out;
    }
    public static function akunPiutang(): AkunKeuangan
    {
        $akun = AkunKeuangan::where('kode_akun', '103.01')->first()
            ?? AkunKeuangan::where('kelompok', 'aset')->where('nama_akun', 'like', '%piutang%')->first();

        if (!$akun) {
            throw new \RuntimeException('Akun Piutang UKT/SPP (103.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        }

        return $akun;
    }

    public static function akunPendapatan(bool $isCalon): AkunKeuangan
    {
        $kode = $isCalon ? '401.02' : '401.01';
        $akun = AkunKeuangan::where('kode_akun', $kode)->first()
            ?? AkunKeuangan::where('kelompok', 'pendapatan')->first();

        if (!$akun) {
            throw new \RuntimeException("Akun Pendapatan ({$kode}) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.");
        }

        return $akun;
    }

    public static function akunKas(bool $isTunai): AkunKeuangan
    {
        $kode = $isTunai ? '101.01' : '102.01';

        return AkunKeuangan::where('kode_akun', $kode)->first()
            ?? AkunKeuangan::where('kelompok', 'aset')->first()
            ?? throw new \RuntimeException("Akun Kas ({$kode}) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.");
    }

    public static function akunBebanPotongan(): AkunKeuangan
    {
        $akun = AkunKeuangan::where('kode_akun', '504.01')->first()
            ?? AkunKeuangan::where('kelompok', 'beban')->first();

        if (!$akun) {
            throw new \RuntimeException('Akun Beban Beasiswa & Potongan (504.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        }

        return $akun;
    }

    /**
     * Akun kas-bank mengikuti kanal UnitKas (tunai/bank_manual/bank_h2h/xendit).
     * Tanpa pemetaan, fallback ke kode default agar transaksi lama tetap berjalan.
     */
    public static function akunKasUnit(?UnitKas $unitKas, string $defaultKode = '101.01'): AkunKeuangan
    {
        if ($unitKas) {
            $terpetakan = $unitKas->akunKeuangan;
            if ($terpetakan && $terpetakan->kelompok === 'aset') {
                return $terpetakan;
            }
        }

        return AkunKeuangan::where('kode_akun', $defaultKode)->first()
            ?? AkunKeuangan::where('kelompok', 'aset')->first()
            ?? throw new \RuntimeException("Akun Kas ({$defaultKode}) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.");
    }

    /**
     * Resolve UnitKas penerima berdasarkan channel pembayaran mahasiswa:
     * - Xendit (VA Xendit / QRIS) -> kanal xendit
     * - VA bank (H2H) -> kanal bank_h2h bank yang sama, fallback bank_manual bank yang sama
     * Mengembalikan null bila tidak ada kanal cocok (caller memakai fallback lama).
     */
    public static function resolveUnitKasUntukChannel(?string $channel, ?string $bankKode): ?UnitKas
    {
        $ch = strtoupper($channel ?? '');
        $bank = strtoupper(trim($bankKode ?? ''));

        if (str_contains($ch, 'XENDIT') || $ch === 'QRIS') {
            return UnitKas::where('kanal', 'xendit')->where('status', true)->first();
        }

        if (in_array($ch, ['TUNAI', 'CASH', 'LOKET_TUNAI'])) {
            return UnitKas::where('kanal', 'tunai')->where('status', true)->first();
        }

        if ($bank !== '') {
            $perBank = fn ($q) => $q->where('bank_name', $bank)->orWhere('bank_name', 'like', "%{$bank}%");

            $h2h = UnitKas::where('kanal', 'bank_h2h')->where('status', true)->where($perBank)->first();
            if ($h2h) {
                return $h2h;
            }

            $manual = UnitKas::where('kanal', 'bank_manual')->where('status', true)->where($perBank)->first();
            if ($manual) {
                return $manual;
            }
        }

        return null;
    }

    protected static function header(string $prefix, string $jenisSumber, ?int $referensiId, string $keterangan, float $total): JurnalUmum
    {
        return JurnalUmum::create([
            'nomor_jurnal' => $prefix . '-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => $jenisSumber,
            'referensi_id' => $referensiId,
            'keterangan' => $keterangan,
            'status_posting' => 'posted',
            'total_debet' => $total,
            'total_kredit' => $total,
            'created_by' => auth()->id(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    protected static function baris(JurnalUmum $jurnal, int $akunId, float $debet, float $kredit, string $keterangan): void
    {
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunId,
            'debet' => $debet,
            'kredit' => $kredit,
            'keterangan' => $keterangan,
        ]);
    }

    /**
     * Penerbitan tagihan: Dr Piutang / Cr Pendapatan.
     */
    public static function jurnalPenerbitanTagihan(TagihanMahasiswa $tagihan): JurnalUmum
    {
        $isCalon = (bool) $tagihan->calon_mahasiswa_id;
        $total = (float) $tagihan->total_tagihan;

        $jurnal = self::header(
            self::prefix('penerbitan'),
            'pembayaran_mahasiswa',
            $tagihan->id,
            "Penerbitan tagihan {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunPiutang()->id, $total, 0, "Piutang tagihan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPendapatan($isCalon)->id, 0, $total, "Pendapatan tagihan {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pembayaran kasir/loket: Dr Kas/Bank / Cr Piutang.
     */
    public static function jurnalPembayaranKasir(Pembayaran $pembayaran, string $channel, TagihanMahasiswa $tagihan, ?UnitKas $unitKas = null): JurnalUmum
    {
        $isTunai = in_array(strtoupper($channel), ['LOKET_TUNAI', 'TUNAI', 'CASH']);
        $channelLabel = $isTunai ? 'Tunai Loket Kasir' : 'Transfer Bank (Non-Tunai)';
        $total = (float) $pembayaran->jumlah_bayar;

        $jurnal = self::header(
            self::prefix('pembayaran'),
            'pembayaran_mahasiswa',
            $pembayaran->id,
            "Pembayaran {$channelLabel} - {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunKasUnit($unitKas, $isTunai ? '101.01' : '102.01')->id, $total, 0, "Penerimaan {$channelLabel} pembayaran mahasiswa" . ($unitKas ? " ({$unitKas->nama_kas})" : ''));
        self::baris($jurnal, self::akunPiutang()->id, 0, $total, "Pelunasan piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Koreksi/pembatalan pembayaran: Dr Piutang / Cr Kas.
     */
    public static function jurnalKoreksiPembayaran(Pembayaran $pembayaran, string $alasan): JurnalUmum
    {
        $channel = $pembayaran->channel_bayar ?? 'LOKET_TUNAI';
        $isTunai = in_array(strtoupper($channel), ['LOKET_TUNAI', 'TUNAI', 'CASH']);
        $total = (float) $pembayaran->jumlah_bayar;

        $jurnal = self::header(
            self::prefix('koreksi'),
            'penyesuaian',
            $pembayaran->id,
            "KOREKSI PEMBATALAN: {$pembayaran->kode_transaksi} - {$alasan}",
            $total
        );

        self::baris($jurnal, self::akunPiutang()->id, $total, 0, "Pengembalian piutang - koreksi {$pembayaran->kode_transaksi}");
        self::baris($jurnal, self::akunKasUnit($pembayaran->unitKas, $isTunai ? '101.01' : '102.01')->id, 0, $total, "Pengembalian kas - koreksi {$pembayaran->kode_transaksi}");

        return $jurnal;
    }

    /**
     * Penghapusan tagihan belum bayar: Dr Pendapatan / Cr Piutang
     * (pembalik jurnal penerbitan). Dilewati bila tidak ada jurnal
     * penerbitan (data lawas pra-akrual) agar buku tidak timpang.
     */
    public static function jurnalPembatalanTagihan(TagihanMahasiswa $tagihan): ?JurnalUmum
    {
        $adaPenerbitan = JurnalUmum::where('jenis_sumber', 'pembayaran_mahasiswa')
            ->where('referensi_id', $tagihan->id)
            ->where('keterangan', 'like', 'Penerbitan tagihan%')
            ->exists();

        if (!$adaPenerbitan) {
            return null;
        }

        $isCalon = (bool) $tagihan->calon_mahasiswa_id;
        $total = (float) $tagihan->total_tagihan;

        $jurnal = self::header(
            self::prefix('pembatalan_tagihan'),
            'penyesuaian',
            $tagihan->id,
            "Pembatalan tagihan {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunPendapatan($isCalon)->id, $total, 0, "Pembalik pendapatan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPiutang()->id, 0, $total, "Pembalik piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pemberian potongan: Dr Beban Beasiswa & Potongan / Cr Piutang.
     */
    public static function jurnalPotongan(TagihanMahasiswa $tagihan, float $nominal, string $keterangan): JurnalUmum
    {
        $jurnal = self::header(
            self::prefix('potongan'),
            'penyesuaian',
            $tagihan->id,
            "Potongan {$tagihan->nomor_tagihan}: {$keterangan}",
            $nominal
        );

        self::baris($jurnal, self::akunBebanPotongan()->id, $nominal, 0, "Beban potongan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPiutang()->id, 0, $nominal, "Pengurang piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pembatalan potongan: Dr Piutang / Cr Beban Beasiswa & Potongan.
     */
    public static function jurnalPembatalanPotongan(TagihanMahasiswa $tagihan, float $nominal, string $keterangan): JurnalUmum
    {
        $jurnal = self::header(
            self::prefix('pembatalan_potongan'),
            'penyesuaian',
            $tagihan->id,
            "Pembatalan potongan {$tagihan->nomor_tagihan}: {$keterangan}",
            $nominal
        );

        self::baris($jurnal, self::akunPiutang()->id, $nominal, 0, "Pengembalian piutang {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunBebanPotongan()->id, 0, $nominal, "Pembalik beban potongan {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Persetujuan dispensasi: jurnal memorandum bernilai nol.
     * Dispensasi (cicilan/penundaan) tidak memindahkan nilai sehingga tidak
     * boleh mengubah saldo; memorandum ini hanya jejak di buku.
     */
    public static function memoDispensasi(DispensasiTagihan $dispensasi): JurnalUmum
    {
        $tagihan = $dispensasi->tagihan;

        return self::header(
            self::prefix('dispensasi_memo'),
            'penyesuaian',
            $dispensasi->id,
            "MEMO: dispensasi {$dispensasi->tipe_dispensasi} disetujui untuk tagihan {$tagihan?->nomor_tagihan} "
                . "(cicilan " . number_format((float) $dispensasi->nominal_per_cicilan, 0, ',', '.')
                . " x {$dispensasi->jumlah_cicilan}, jatuh tempo baru {$dispensasi->jatuh_tempo_baru}). Tanpa efek saldo.",
            0
        );
    }

    /**
     * Realisasi belanja operasional (LPJ diverifikasi keuangan):
     * Dr Beban Operasional (502.01) / Cr Kas (101.01).
     * Dipakai saat LPJ pengajuan operasional disetujui.
     */
    public static function jurnalRealisasiOperasional(int $referensiId, float $nominal, string $keterangan, string $kodeBeban = '502.01'): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal realisasi LPJ harus lebih dari nol.');
        }

        $akunBeban = AkunKeuangan::where('kode_akun', $kodeBeban)->first()
            ?? AkunKeuangan::where('kelompok', 'beban')->first();
        $akunKas = AkunKeuangan::where('kode_akun', '101.01')->first()
            ?? AkunKeuangan::where('kelompok', 'aset')->first();

        if (!$akunBeban || !$akunKas) {
            throw new \RuntimeException('Akun Beban (502.01) atau Kas (101.01) belum dikonfigurasi di COA.');
        }

        $jurnal = self::header(
            self::prefix('pengeluaran'),
            'pencairan_kas',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban operasional: {$keterangan}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Kas keluar realisasi: {$keterangan}");

        return $jurnal;
    }

    /**
     * Transaksi pengeluaran kas kecil (petty cash):
     * Dr Beban (502.01) / Cr Kas Unit (akun COA milik UnitKas kas kecil).
     * Dipanggil saat Petugas Kas Kecil mencatat transaksi keluar.
     */
    public static function jurnalKeluarKasKecil(int $referensiId, float $nominal, string $keterangan, ?UnitKas $unitKas = null): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal transaksi keluar kas kecil harus lebih dari nol.');
        }

        $akunBeban = AkunKeuangan::where('kode_akun', '502.01')->first()
            ?? AkunKeuangan::where('kelompok', 'beban')->first();
        $akunKas = self::akunKasUnit($unitKas, '101.01');

        if (!$akunBeban) {
            throw new \RuntimeException('Akun Beban (502.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        }

        $jurnal = self::header(
            self::prefix('pengeluaran'),
            'pengeluaran_manual',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban kas kecil: {$keterangan}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Kas kecil keluar: {$keterangan}");

        return $jurnal;
    }

    /**
     * Pengisian / top-up kas kecil saat pengajuan kas langsung disetujui:
     * Dr Kas Unit (akun COA milik UnitKas kas kecil) / Cr Kas Utama (101.01).
     * Membalik posisi saldo: kas kecil bertambah, kas utama berkurang.
     */
    public static function jurnalPengisianKasKecil(int $referensiId, float $nominal, string $keterangan, ?UnitKas $unitKas = null): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal pengisian kas kecil harus lebih dari nol.');
        }

        $akunKasUtama = AkunKeuangan::where('kode_akun', '101.01')->first()
            ?? AkunKeuangan::where('kelompok', 'aset')->first();
        $akunKasUnit = self::akunKasUnit($unitKas, '101.01');

        if (!$akunKasUtama) {
            throw new \RuntimeException('Akun Kas Utama (101.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        }

        // Pengisian kas kecil harus memindahkan dana ANTAR akun kas yang berbeda.
        // Jika akun kas unit sama dengan kas utama, jurnal menjadi netral & tidak bermakna.
        if ($akunKasUnit->id === $akunKasUtama->id) {
            throw new \RuntimeException(
                'Unit kas kecil belum dipetakan ke akun COA kas tersendiri (mis. 101.02 Kas Unit / Petty Cash). '
                . 'Jurnal pengisian kas kecil dibatalkan.'
            );
        }

        $jurnal = self::header(
            self::prefix('pemasukan'),
            'pencairan_kas',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunKasUnit->id, $nominal, 0, "Pengisian kas kecil: {$keterangan}");
        self::baris($jurnal, $akunKasUtama->id, 0, $nominal, "Kas utama: {$keterangan}");

        return $jurnal;
    }
}
