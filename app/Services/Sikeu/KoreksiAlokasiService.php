<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\KoreksiAlokasi;
use App\Models\Sikeu\KoreksiAlokasiDetail;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\PeriodeAkuntansi;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Menu Koreksi Alokasi Pembayaran.
 *
 * Redistribusi ALOKASI antar tagihan milik mahasiswa yang sama.
 * Pembayaran (kas) tidak diubah; yang dipindah hanya porsi
 * detail_tagihan.terbayar + tagihan.total_bayar.
 * Kekurangan target otomatis diusulkan sebagai tagihan baru.
 */
class KoreksiAlokasiService
{
    private function catatAudit(string $action, string $table, ?int $recordId, ?array $old = null, ?array $new = null): void
    {
        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: $action,
                tableName: $table,
                recordId: $recordId,
                oldValues: $old,
                newValues: $new,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log koreksi alokasi: ' . $e->getMessage());
        }
    }

    public static function isPeriodeTutup(?string $tanggal = null): ?PeriodeAkuntansi
    {
        $tgl = $tanggal ?? now()->toDateString();

        return PeriodeAkuntansi::where('status', 'ditutup')
            ->where('tanggal_mulai', '<=', $tgl)
            ->where('tanggal_selesai', '>=', $tgl)
            ->first();
    }

    public static function totalBersih(TagihanMahasiswa $tagihan): float
    {
        return max(0, (float) $tagihan->total_tagihan + (float) ($tagihan->total_denda ?? 0) - (float) $tagihan->total_potongan);
    }

    public static function sisa(TagihanMahasiswa $tagihan): float
    {
        return max(0, self::totalBersih($tagihan) - (float) $tagihan->total_bayar);
    }

    public static function hitungStatus(TagihanMahasiswa $tagihan, float $newTotalBayar): string
    {
        $bersih = self::totalBersih($tagihan);

        if ($bersih <= 0 || $newTotalBayar >= $bersih - 0.01) {
            return 'lunas';
        }
        if ($newTotalBayar > 0) {
            return in_array($tagihan->status, ['dispensasi', 'pending_approval'], true)
                ? $tagihan->status
                : 'sebagian';
        }

        return 'belum_bayar';
    }

    /**
     * Dana yang dicadangkan koreksi pending/approved (belum applied)
     * agar draft ganda tidak over-allocate sumber yang sama.
     */
    public static function reservedKeluar(int $fromTagihanId, ?int $exceptKoreksiId = null): float
    {
        $q = KoreksiAlokasiDetail::where('from_tagihan_id', $fromTagihanId)
            ->whereHas('koreksi', fn ($k) => $k->whereIn('status', ['pending', 'approved']));
        if ($exceptKoreksiId) {
            $q->where('koreksi_alokasi_id', '!=', $exceptKoreksiId);
        }

        return (float) $q->sum('nominal_dialihkan');
    }

    public static function tersediaDialihkan(TagihanMahasiswa $source, ?int $exceptKoreksiId = null): float
    {
        return max(0, (float) $source->total_bayar - self::reservedKeluar($source->id, $exceptKoreksiId));
    }

    /**
     * Konsolidasi tagihan milik satu mahasiswa/calon untuk UI koreksi.
     */
    public function konsolidasi(?int $mahasiswaId, ?int $calonMahasiswaId = null): array
    {
        $query = TagihanMahasiswa::with(['detailTagihan.masterBiaya'])
            ->orderBy('id', 'asc');

        if ($mahasiswaId) {
            $query->where('mahasiswa_id', $mahasiswaId);
        } elseif ($calonMahasiswaId) {
            $query->where('calon_mahasiswa_id', $calonMahasiswaId);
        } else {
            return ['tagihan' => [], 'ringkasan' => $this->ringkasanKosong()];
        }

        $tagihans = $query->get();

        $items = $tagihans->map(function (TagihanMahasiswa $t) {
            $bersih = self::totalBersih($t);
            $sisa = self::sisa($t);

            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'tahun_akademik_id' => $t->tahun_akademik_id,
                'semester' => $t->semester,
                'total_tagihan' => (float) $t->total_tagihan,
                'total_potongan' => (float) $t->total_potongan,
                'total_denda' => (float) $t->total_denda,
                'total_bayar' => (float) $t->total_bayar,
                'total_bersih' => $bersih,
                'sisa' => $sisa,
                'status' => $t->status,
                'jatuh_tempo' => $t->jatuh_tempo ? (string) $t->jatuh_tempo : null,
                'tersedia_dialihkan' => self::tersediaDialihkan($t),
                'rincian' => $t->detailTagihan->map(fn ($d) => [
                    'id' => $d->id,
                    'master_biaya_id' => $d->master_biaya_id,
                    'nama_biaya' => $d->masterBiaya?->nama ?? 'Komponen Biaya',
                    'nominal_bersih' => (float) $d->nominal_bersih,
                    'terbayar' => (float) $d->terbayar,
                    'sisa' => max(0, (float) $d->nominal_bersih - (float) $d->terbayar),
                ])->values()->all(),
            ];
        })->values()->all();

        $totalTagihan = array_sum(array_column($items, 'total_bersih'));
        $totalBayar = array_sum(array_column($items, 'total_bayar'));
        $totalSisa = array_sum(array_column($items, 'sisa'));

        return [
            'tagihan' => $items,
            'ringkasan' => [
                'total_tagihan_bersih' => $totalTagihan,
                'total_terbayar' => $totalBayar,
                'total_sisa' => $totalSisa,
                'jumlah_tagihan' => count($items),
            ],
        ];
    }

    private function ringkasanKosong(): array
    {
        return [
            'total_tagihan_bersih' => 0,
            'total_terbayar' => 0,
            'total_sisa' => 0,
            'jumlah_tagihan' => 0,
        ];
    }

    /**
     * Validasi + simulasi dampak rencana koreksi.
     * Return: per-target sisa_setelah + usulan kekurangan.
     *
     * @param  array{details: array<int, array{from_tagihan_id:int,to_tagihan_id:int,nominal_dialihkan:float|int}>}  $payload
     */
    public function simulate(array $payload, ?int $exceptKoreksiId = null): array
    {
        $details = $payload['details'] ?? [];
        if (empty($details)) {
            throw new \InvalidArgumentException('Rencana koreksi minimal memuat 1 rincian perpindahan.');
        }

        $fromIds = collect($details)->pluck('from_tagihan_id')->unique()->values()->all();
        $toIds = collect($details)->pluck('to_tagihan_id')->unique()->values()->all();

        $sources = TagihanMahasiswa::whereIn('id', $fromIds)->get()->keyBy('id');
        $targets = TagihanMahasiswa::whereIn('id', $toIds)->get()->keyBy('id');

        // 1. Semua tagihan harus milik mahasiswa yang sama.
        $owners = [];
        foreach (array_merge($fromIds, $toIds) as $tid) {
            $t = $sources->get($tid) ?? $targets->get($tid);
            if (!$t) {
                throw new \InvalidArgumentException("Tagihan ID {$tid} tidak ditemukan.");
            }
            $owners[] = $t->mahasiswa_id ? ('mhs:' . $t->mahasiswa_id) : ('calon:' . $t->calon_mahasiswa_id);
        }
        if (count(array_unique($owners)) > 1) {
            throw new \InvalidArgumentException('Koreksi hanya boleh antar tagihan milik mahasiswa yang sama.');
        }

        // 2. Validasi per rincian.
        $keluarPerSource = [];
        foreach ($details as $i => $d) {
            $fromId = (int) ($d['from_tagihan_id'] ?? 0);
            $toId = (int) ($d['to_tagihan_id'] ?? 0);
            $nominal = (float) ($d['nominal_dialihkan'] ?? 0);

            if ($fromId === $toId) {
                throw new \InvalidArgumentException("Rincian #{$i}: tagihan asal dan tujuan tidak boleh sama.");
            }
            if ($nominal <= 0) {
                throw new \InvalidArgumentException("Rincian #{$i}: nominal harus lebih dari nol.");
            }

            $source = $sources->get($fromId);
            $target = $targets->get($toId);

            if (in_array($source->status, ['batal'], true) || in_array($target->status, ['batal'], true)) {
                throw new \InvalidArgumentException('Tagihan berstatus batal tidak boleh dikoreksi.');
            }
            if ((float) $source->total_bayar <= 0) {
                throw new \InvalidArgumentException("Tagihan asal {$source->nomor_tagihan} belum memiliki alokasi yang bisa dipindahkan.");
            }

            $keluarPerSource[$fromId] = ($keluarPerSource[$fromId] ?? 0) + $nominal;
        }

        // 3. Total keluar per source tidak boleh melebihi tersedia (dikurangi reservasi).
        foreach ($keluarPerSource as $fromId => $totalKeluar) {
            $source = $sources->get($fromId);
            $tersedia = self::tersediaDialihkan($source, $exceptKoreksiId);
            if ($totalKeluar > $tersedia + 0.01) {
                throw new \InvalidArgumentException(
                    "Total dialihkan dari {$source->nomor_tagihan} (Rp " . number_format($totalKeluar, 0, ',', '.') .
                    ") melebihi alokasi tersedia (Rp " . number_format($tersedia, 0, ',', '.') . ")."
                );
            }
        }

        // 4. Hitung dampak per target.
        $masukPerTarget = [];
        foreach ($details as $d) {
            $toId = (int) $d['to_tagihan_id'];
            $masukPerTarget[$toId] = ($masukPerTarget[$toId] ?? 0) + (float) $d['nominal_dialihkan'];
        }

        $dampak = [];
        $totalKekurangan = 0;
        foreach ($masukPerTarget as $toId => $masuk) {
            $target = $targets->get($toId);
            $sisaSebelum = self::sisa($target);
            if ($sisaSebelum <= 0) {
                throw new \InvalidArgumentException("Tagihan tujuan {$target->nomor_tagihan} sudah lunas.");
            }
            $terbayarSesudah = min($sisaSebelum, $masuk);
            $sisaSesudah = max(0, $sisaSebelum - $masuk);
            $totalKekurangan += $sisaSesudah;

            $dampak[] = [
                'to_tagihan_id' => $toId,
                'nomor_tagihan' => $target->nomor_tagihan,
                'total_bersih' => self::totalBersih($target),
                'terbayar_sebelum' => (float) $target->total_bayar,
                'sisa_sebelum' => $sisaSebelum,
                'dialihkan_masuk' => $masuk,
                'sisa_setelah' => $sisaSesudah,
                'akan_lunas' => $sisaSesudah <= 0.01,
            ];
        }

        $totalDialihkan = array_sum(array_column($details, 'nominal_dialihkan'));

        return [
            'total_dialihkan' => (float) $totalDialihkan,
            'dampak_target' => $dampak,
            'ada_kekurangan' => $totalKekurangan > 0.01,
            'total_kekurangan' => (float) $totalKekurangan,
            'usulan_tagihan_kekurangan' => $totalKekurangan > 0.01
                ? 'Buat tagihan koreksi kekurangan Rp ' . number_format($totalKekurangan, 0, ',', '.')
                : null,
        ];
    }

    public function createDraft(int $actorId, array $payload): KoreksiAlokasi
    {
        if ($tutup = self::isPeriodeTutup()) {
            throw new \RuntimeException("Periode \"{$tutup->nama_periode}\" sudah ditutup. Koreksi tidak dapat dibuat.");
        }

        $simulasi = $this->simulate($payload);

        return DB::transaction(function () use ($actorId, $payload, $simulasi) {
            $firstDetail = $payload['details'][0];
            $firstTagihan = TagihanMahasiswa::findOrFail($firstDetail['from_tagihan_id']);

            $nomor = $this->nomorBaru();

            $koreksi = KoreksiAlokasi::create([
                'mahasiswa_id' => $firstTagihan->mahasiswa_id,
                'calon_mahasiswa_id' => $firstTagihan->calon_mahasiswa_id,
                'tipe_referensi' => $firstTagihan->tipe_referensi ?? 'mahasiswa',
                'nomor_koreksi' => $nomor,
                'tahun_akademik_id' => $payload['tahun_akademik_id'] ?? $firstTagihan->tahun_akademik_id,
                'semester' => $payload['semester'] ?? $firstTagihan->semester,
                'alasan' => $payload['alasan'],
                'referensi' => $payload['referensi'] ?? null,
                'status' => 'pending',
                'requested_by' => $actorId,
                'total_nominal_dialihkan' => $simulasi['total_dialihkan'],
                'buat_tagihan_kekurangan' => (bool) ($payload['buat_tagihan_kekurangan'] ?? true),
            ]);

            foreach ($payload['details'] as $d) {
                KoreksiAlokasiDetail::create([
                    'koreksi_alokasi_id' => $koreksi->id,
                    'from_tagihan_id' => (int) $d['from_tagihan_id'],
                    'from_detail_tagihan_id' => isset($d['from_detail_tagihan_id']) ? (int) $d['from_detail_tagihan_id'] : null,
                    'to_tagihan_id' => (int) $d['to_tagihan_id'],
                    'to_detail_tagihan_id' => isset($d['to_detail_tagihan_id']) ? (int) $d['to_detail_tagihan_id'] : null,
                    'nominal_dialihkan' => (float) $d['nominal_dialihkan'],
                    'keterangan' => $d['keterangan'] ?? null,
                ]);
            }

            $this->catatAudit('create', 'sikeu_koreksi_alokasi', $koreksi->id, null, $koreksi->getChanges());

            return $koreksi->fresh(['details']);
        });
    }

    public function approve(KoreksiAlokasi $koreksi, int $actorId, ?string $catatan = null): KoreksiAlokasi
    {
        if ($koreksi->status !== 'pending') {
            throw new \RuntimeException('Hanya koreksi berstatus pending yang bisa disetujui.');
        }
        // Re-validasi simulasi saat approve (mencegah race dengan koreksi lain).
        $this->simulate(['details' => $koreksi->details->map(fn ($d) => [
            'from_tagihan_id' => $d->from_tagihan_id,
            'to_tagihan_id' => $d->to_tagihan_id,
            'nominal_dialihkan' => (float) $d->nominal_dialihkan,
        ])->all()], $koreksi->id);

        $oldApprove = $koreksi->getOriginal();
        $koreksi->update([
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'catatan_approval' => $catatan ?? $koreksi->catatan_approval,
        ]);
        $this->catatAudit('approve', 'sikeu_koreksi_alokasi', $koreksi->id, $oldApprove, $koreksi->getChanges());

        return $koreksi->fresh(['details']);
    }

    public function reject(KoreksiAlokasi $koreksi, int $actorId, ?string $catatan = null): KoreksiAlokasi
    {
        if (!in_array($koreksi->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('Hanya koreksi pending/approved yang bisa ditolak.');
        }
        $oldReject = $koreksi->getOriginal();
        $koreksi->update([
            'status' => 'rejected',
            'rejected_by' => $actorId,
            'rejected_at' => now(),
            'catatan_approval' => $catatan ?? $koreksi->catatan_approval,
        ]);
        $this->catatAudit('reject', 'sikeu_koreksi_alokasi', $koreksi->id, $oldReject, $koreksi->getChanges());

        return $koreksi->fresh(['details']);
    }

    public function cancel(KoreksiAlokasi $koreksi, int $actorId): KoreksiAlokasi
    {
        if (!in_array($koreksi->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('Hanya koreksi pending/approved yang bisa dibatalkan.');
        }
        $oldCancel = $koreksi->getOriginal();
        $koreksi->update(['status' => 'cancelled']);
        $this->catatAudit('reject', 'sikeu_koreksi_alokasi', $koreksi->id, $oldCancel, $koreksi->getChanges());

        return $koreksi->fresh(['details']);
    }

    /**
     * Terapkan koreksi secara atomik + terbitkan tagihan kekurangan bila perlu.
     */
    public function apply(KoreksiAlokasi $koreksi, int $actorId): array
    {
        if ($koreksi->status !== 'approved') {
            throw new \RuntimeException('Hanya koreksi yang sudah disetujui yang bisa diterapkan.');
        }
        if ($tutup = self::isPeriodeTutup()) {
            throw new \RuntimeException("Periode \"{$tutup->nama_periode}\" sudah ditutup. Koreksi tidak dapat diterapkan.");
        }

        return DB::transaction(function () use ($koreksi, $actorId) {
            $koreksi->loadMissing(['details']);
            // Double-apply guard di dalam transaksi.
            $fresh = KoreksiAlokasi::where('id', $koreksi->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'approved') {
                throw new \RuntimeException('Koreksi sudah diproses sebelumnya (double-apply dicegah).');
            }
            $oldKoreksiApply = $fresh->getOriginal();

            // Validasi ulang terhadap kondisi terkini.
            $this->simulate(['details' => $fresh->details->map(fn ($d) => [
                'from_tagihan_id' => $d->from_tagihan_id,
                'to_tagihan_id' => $d->to_tagihan_id,
                'nominal_dialihkan' => (float) $d->nominal_dialihkan,
            ])->all()], $fresh->id);

            // Agregasi per target untuk 1 record pembayaran per tagihan tujuan.
            $perTarget = [];
            foreach ($fresh->details as $d) {
                $perTarget[$d->to_tagihan_id] = ($perTarget[$d->to_tagihan_id] ?? 0) + (float) $d->nominal_dialihkan;
            }

            // 1. Kurangi sumber.
            foreach ($fresh->details as $d) {
                $source = TagihanMahasiswa::where('id', $d->from_tagihan_id)->lockForUpdate()->firstOrFail();
                $oldSource = $source->getOriginal();
                DetailTagihan::kurangi($source, (float) $d->nominal_dialihkan);
                $newBayar = max(0, (float) $source->total_bayar - (float) $d->nominal_dialihkan);
                $source->update([
                    'total_bayar' => $newBayar,
                    'status' => self::hitungStatus($source, $newBayar),
                ]);
                $this->catatAudit('update', 'sikeu_tagihan_mahasiswa', $source->id, $oldSource, $source->getChanges());
            }

            // 2. Tambah tujuan + catat pembayaran PENGALIHAN_DANA.
            $kekurangan = [];
            foreach ($perTarget as $toId => $masuk) {
                $target = TagihanMahasiswa::where('id', $toId)->lockForUpdate()->firstOrFail();
                $oldTarget = $target->getOriginal();
                DetailTagihan::alokasikan($target, $masuk);
                $newBayar = (float) $target->total_bayar + $masuk;
                $target->update([
                    'total_bayar' => $newBayar,
                    'status' => self::hitungStatus($target, $newBayar),
                ]);
                $this->catatAudit('update', 'sikeu_tagihan_mahasiswa', $target->id, $oldTarget, $target->getChanges());

                $bayar = Pembayaran::create([
                    'tagihan_id' => $target->id,
                    'kode_transaksi' => 'KOREKSI-' . date('YmdHis') . '-' . strtoupper(Str::random(3)),
                    'jumlah_bayar' => $masuk,
                    'waktu_bayar' => now(),
                    'channel_bayar' => 'PENGALIHAN_DANA',
                    'bank_pengirim' => 'INTERNAL_KAMPUS',
                    'catatan' => "Koreksi alokasi {$fresh->nomor_koreksi}",
                    'status' => 'success',
                    'diverifikasi_oleh' => $actorId,
                ]);
                $this->catatAudit('create', 'sikeu_pembayaran', $bayar->id, null, $bayar->getChanges());

                $sisaSesudah = self::sisa($target->fresh());
                if ($fresh->buat_tagihan_kekurangan && $sisaSesudah > 0.01) {
                    $kekurangan[] = $this->terbitkanTagihanKekurangan($fresh, $target->fresh(), $sisaSesudah);
                }
            }

            $fresh->update(['status' => 'applied', 'applied_at' => now()]);
            $this->catatAudit('approve', 'sikeu_koreksi_alokasi', $fresh->id, $oldKoreksiApply, $fresh->getChanges());

            return [
                'koreksi' => $fresh->fresh(['details', 'tagihanKekurangan']),
                'tagihan_kekurangan' => $kekurangan,
            ];
        });
    }

    private function terbitkanTagihanKekurangan(KoreksiAlokasi $koreksi, TagihanMahasiswa $target, float $sisa): TagihanMahasiswa
    {
        // Komponen acuan: detail target yang masih bersisa, fallback detail pertama.
        $acuan = $target->detailTagihan()->orderBy('id')->get()
            ->first(fn ($d) => ((float) $d->nominal_bersih - (float) $d->terbayar) > 0.01)
            ?? $target->detailTagihan()->orderBy('id')->first();

        $masterBiayaId = $acuan?->master_biaya_id
            ?? MasterBiaya::where('is_active', true)->orderBy('id')->value('id');

        if (!$masterBiayaId) {
            throw new \RuntimeException('Katalog biaya aktif tidak ditemukan untuk tagihan kekurangan.');
        }

        $nomor = 'KOR-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        $baru = TagihanMahasiswa::create([
            'mahasiswa_id' => $target->mahasiswa_id,
            'calon_mahasiswa_id' => $target->calon_mahasiswa_id,
            'tipe_referensi' => $target->tipe_referensi ?? 'mahasiswa',
            'tahun_akademik_id' => $target->tahun_akademik_id,
            'semester' => $target->semester,
            'nomor_tagihan' => $nomor,
            'total_tagihan' => $sisa,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'requires_approval' => false,
            'source_system' => 'sikeu_koreksi_alokasi',
            'koreksi_alokasi_id' => $koreksi->id,
            'jatuh_tempo' => $target->jatuh_tempo?->toDateString() ?? now()->addDays(30)->toDateString(),
        ]);

        DetailTagihan::create([
            'tagihan_id' => $baru->id,
            'master_biaya_id' => $masterBiayaId,
            'nominal' => $sisa,
            'potongan' => 0,
            'nominal_bersih' => $sisa,
            'keterangan' => "Kekurangan koreksi {$koreksi->nomor_koreksi} atas {$target->nomor_tagihan}",
        ]);
        $this->catatAudit('create', 'sikeu_tagihan_mahasiswa', $baru->id, null, $baru->getChanges());

        JurnalSikeuService::jurnalPenerbitanTagihan($baru);

        return $baru;
    }

    private function nomorBaru(): string
    {
        do {
            $nomor = 'KOR-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (KoreksiAlokasi::where('nomor_koreksi', $nomor)->exists());

        return $nomor;
    }
}
