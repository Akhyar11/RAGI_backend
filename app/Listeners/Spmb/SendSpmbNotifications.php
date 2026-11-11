<?php

namespace App\Listeners\Spmb;

use App\Jobs\PublishSpmbResultNotification;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Spmb\SpmbNotificationService;
use App\Services\Spmb\SpmbPaymentStatusService;

/** Synchronous inbox writes share the originating transaction; mail is queued after commit. */
class SendSpmbNotifications
{
    public function __construct(private readonly SpmbNotificationService $notifications) {}

    public function applicationSaved(PendaftaranCalonMhs $application): void
    {
        $this->notifications->applicationChanged($application);
    }

    public function resultSaved(HasilSeleksi $result): void
    {
        if ($result->wasRecentlyCreated || $result->wasChanged(['status', 'program_studi_diterima_id', 'diumumkan_at'])) {
            if ($result->diumumkan_at?->isFuture()) {
                PublishSpmbResultNotification::dispatch($result->id)->delay($result->diumumkan_at)->afterCommit();
            } else {
                $this->notifications->resultPublished($result);
            }
        }
        $this->notifications->reenrollmentChanged($result);
    }

    public function accountSaved(VirtualAccount $account): void
    {
        $this->notifications->virtualAccountChanged($account);
    }

    public function billSaved(TagihanMahasiswa $bill): void
    {
        $this->notifications->billChanged($bill);
        if ($bill->source_system === 'SPMB' && $bill->calon_mahasiswa_id && $bill->wasChanged(['status', 'total_bayar'])) {
            app(SpmbPaymentStatusService::class)->sync($bill->calon_mahasiswa_id,
                $bill->tipe_referensi === TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG);
        }
    }
}
