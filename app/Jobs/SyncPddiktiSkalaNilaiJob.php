<?php

namespace App\Jobs;

use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\IAM\SkalaNilaiIntegrationSyncService;

class SyncPddiktiSkalaNilaiJob extends AbstractSyncPddiktiJob
{
    protected function service(): QueuedPddiktiSyncService
    {
        return app(SkalaNilaiIntegrationSyncService::class);
    }
}
