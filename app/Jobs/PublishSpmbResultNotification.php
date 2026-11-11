<?php

namespace App\Jobs;

use App\Models\Spmb\HasilSeleksi;
use App\Services\Spmb\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishSpmbResultNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $resultId)
    {
        $this->onQueue('notifications');
    }

    public function handle(SpmbNotificationService $notifications): void
    {
        $result = HasilSeleksi::find($this->resultId);
        if ($result) {
            $notifications->resultPublished($result);
        }
    }
}
