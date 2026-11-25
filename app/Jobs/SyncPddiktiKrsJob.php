<?php

namespace App\Jobs;

use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\IAM\KrsHistorisIntegrationSyncService;

class SyncPddiktiKrsJob extends AbstractSyncPddiktiJob
{
    protected function service(): QueuedPddiktiSyncService
    {
        return app(KrsHistorisIntegrationSyncService::class);
    }
}
