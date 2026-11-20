<?php

namespace App\Jobs;

use App\Models\IamApiIntegration;
use App\Services\AuditLogService;
use App\Services\IAM\ApiIntegrationSyncException;
use App\Services\IAM\SiakadMahasiswaIntegrationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPddiktiMahasiswaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public readonly int $integrationId,
        public readonly string $progressId,
        public readonly string $lockOwner,
        public readonly ?int $requestedBy = null
    ) {
        $this->onQueue('default');
    }

    public function handle(SiakadMahasiswaIntegrationSyncService $syncService): void
    {
        try {
            $integration = IamApiIntegration::findOrFail($this->integrationId);
            $syncService->markProgressRunning($this->progressId, 'Worker mulai menjalankan sinkronisasi.');
            $result = $syncService->syncPddikti(
                $integration,
                progressId: $this->progressId,
                actorId: $this->requestedBy,
            );
            $syncService->completeProgress($this->progressId, $result);
            $this->updateIntegrationStatus($integration, ($result['failed'] ?? 0) > 0 ? 'failed' : 'success');
        } catch (Throwable $exception) {
            Log::error('Background sync mahasiswa PDDIKTI gagal.', [
                'integration_id' => $this->integrationId,
                'exception' => $exception::class,
            ]);
            $syncService->markProgressFailed(
                $this->progressId,
                $exception instanceof ApiIntegrationSyncException
                    ? $exception->getMessage()
                    : 'Sinkronisasi gagal. Periksa log aplikasi untuk detail teknis.'
            );
            $integration = IamApiIntegration::find($this->integrationId);
            if ($integration) {
                $this->updateIntegrationStatus($integration, 'failed');
            }
        } finally {
            cache()->restoreLock('siakad-mahasiswa-pddikti-sync:lock', $this->lockOwner)->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        cache()->put('siakad-mahasiswa-pddikti-sync:' . $this->progressId, array_merge(cache()->get('siakad-mahasiswa-pddikti-sync:' . $this->progressId, []), [
            'status' => 'failed',
            'phase' => 'failed',
            'message' => 'Worker gagal menjalankan sinkronisasi. Periksa konfigurasi queue worker.',
            'finished_at' => now()->toISOString(),
        ]), now()->addHours(2));
        $integration = IamApiIntegration::find($this->integrationId);
        if ($integration) {
            $this->updateIntegrationStatus($integration, 'failed');
        }
        cache()->restoreLock('siakad-mahasiswa-pddikti-sync:lock', $this->lockOwner)->release();
    }

    private function updateIntegrationStatus(IamApiIntegration $integration, string $status): void
    {
        $oldValues = $integration->only(['last_synced_at', 'last_status']);
        $integration->update([
            'last_synced_at' => now(),
            'last_status' => $status,
        ]);
        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_' . $status,
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: $integration->only(['last_synced_at', 'last_status']),
                actorId: $this->requestedBy,
            );
        } catch (Throwable $auditException) {
            Log::warning('Gagal mencatat audit perubahan status sync PDDIKTI.', [
                'integration_id' => $integration->id,
                'exception' => $auditException::class,
            ]);
        }
    }

}
