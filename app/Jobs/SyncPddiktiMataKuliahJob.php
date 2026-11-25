<?php

namespace App\Jobs;

use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\IAM\MataKuliahIntegrationSyncService;

class SyncPddiktiMataKuliahJob extends AbstractSyncPddiktiJob
{
    protected function service(): QueuedPddiktiSyncService
    {
        return app(MataKuliahIntegrationSyncService::class);
    }
}
