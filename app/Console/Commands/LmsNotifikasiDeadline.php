<?php

namespace App\Console\Commands;

use App\Services\Siakad\LmsNotificationService;
use Illuminate\Console\Command;

/**
 * Kirim pengingat deadline tugas & kuis ke mahasiswa.
 *
 * Dijadwalkan harian (lihat routes/console.php). Notifikasi bersifat
 * idempoten: pengingat yang sama tidak dibuat dua kali pada hari yang sama
 * (dicatat lewat `dedup_key`).
 */
class LmsNotifikasiDeadline extends Command
{
    protected $signature = 'lms:notifikasi-deadline
        {--hours=24 : Rentang waktu pengingat ke depan (jam)}
        {--dry-run : Hanya tampilkan ringkasan, tanpa membuat notifikasi}';

    protected $description = 'Kirim pengingat deadline tugas & kuis ke mahasiswa yang terdaftar';

    public function handle(LmsNotificationService $notificationService): int
    {
        $jam = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line("Mode dry-run — rentang pengingat {$jam} jam ke depan.");

            return self::SUCCESS;
        }

        $tugas = $notificationService->pushDeadlineTugas($jam);
        $quiz = $notificationService->pushDeadlineQuiz($jam);

        $this->info(sprintf(
            'Notifikasi deadline: %d tugas (%d notifikasi), %d kuis (%d notifikasi).',
            $tugas['tugas'],
            $tugas['notifikasi'],
            $quiz['quiz'],
            $quiz['notifikasi']
        ));

        $outbound = $tugas['outbound'] + $quiz['outbound'];
        if ($outbound > 0) {
            $this->info("Kanal outbound (email/WhatsApp): {$outbound} pesan terkirim.");
        }

        return self::SUCCESS;
    }
}
