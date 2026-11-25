<?php

namespace App\Jobs;

use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use App\Services\IAM\ProgramStudiIntegrationSyncService;

class SyncPddiktiProgramStudiJob extends AbstractSyncPddiktiJob
{
    protected function service(): QueuedPddiktiSyncService
    {
        return app(ProgramStudiIntegrationSyncService::class);
    }
}
