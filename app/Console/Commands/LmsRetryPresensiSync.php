<?php

namespace App\Console\Commands;

use App\Models\Siakad\Pertemuan;
use App\Services\Siakad\PresensiV2SyncService;
use Illuminate\Console\Command;

/**
 * Coba ulang sinkronisasi LMS -> PresensiV2 untuk pertemuan yang gagal.
 *
 * Dijadwalkan hourly (lihat routes/console.php) sehingga sesi yang gagal
 * karena masalah jaringan sementara otomatis dicoba lagi.
 */
class LmsRetryPresensiSync extends Command
{
    protected $signature = 'lms:retry-presensi-sync
        {--limit=100 : Maksimal pertemuan yang dicoba ulang}
        {--dry-run : Hanya tampilkan yang akan dicoba, tanpa eksekusi}';

    protected $description = 'Coba ulang sinkronisasi jurnal + kehadiran ke PresensiV2 yang berstatus gagal';

    public function handle(PresensiV2SyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->warn('Integrasi PresensiV2 belum dikonfigurasi — tidak ada yang dicoba.');
            $this->line('Isi PRESENSI_API_URL (jalur API) atau kredensial koneksi database PresensiV2 (jalur DB).');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $pertemuans = Pertemuan::query()
            ->whereIn('sync_status', [Pertemuan::SYNC_FAILED, Pertemuan::SYNC_PENDING])
            ->whereNotNull('tanggal')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($pertemuans->isEmpty()) {
            $this->info('Tidak ada pertemuan gagal yang perlu dicoba ulang.');

            return self::SUCCESS;
        }

        $this->info('Menemukan '.$pertemuans->count().' pertemuan untuk dicoba ulang.');

        if ($dryRun) {
            foreach ($pertemuans as $pertemuan) {
                $this->line(sprintf(
                    ' - pertemuan #%d kelas %d P%d (%s)',
                    $pertemuan->id,
                    $pertemuan->kelas_id,
                    $pertemuan->pertemuan_ke,
                    $pertemuan->sync_status
                ));
            }

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($pertemuans as $pertemuan) {
            $result = $sync->syncKehadiran($pertemuan);

            if ($result['success'] ?? false) {
                $ok++;
                $this->info('OK pertemuan #'.$pertemuan->id.': '.($result['message'] ?? 'selesai'));
            } else {
                $fail++;
                $this->error('GAGAL pertemuan #'.$pertemuan->id.': '.($result['message'] ?? 'tidak diketahui'));
            }
        }

        $this->info("Selesai. Berhasil: {$ok}, gagal: {$fail}.");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
