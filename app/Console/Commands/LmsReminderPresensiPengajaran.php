<?php

namespace App\Console\Commands;

use App\Services\Siakad\LmsNotificationService;
use Illuminate\Console\Command;

class LmsReminderPresensiPengajaran extends Command
{
    protected $signature = 'lms:reminder-presensi-pengajaran';

    protected $description = 'Kirim pengingat harian kepada dosen untuk presensi pertemuan yang belum diisi';

    public function handle(LmsNotificationService $notificationService): int
    {
        $result = $notificationService->pushReminderPresensiPengajaran();

        $this->info(sprintf(
            'Pengingat presensi pengajaran: %d pertemuan, %d notifikasi.',
            $result['pertemuan'],
            $result['notifikasi']
        ));

        return self::SUCCESS;
    }
}
