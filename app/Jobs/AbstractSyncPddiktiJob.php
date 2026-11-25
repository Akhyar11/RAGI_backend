<?php

namespace App\Jobs;

use App\Models\IamApiIntegration;
use App\Services\AuditLogService;
use App\Services\IAM\ApiIntegrationSyncException;
use App\Services\IAM\Contracts\QueuedPddiktiSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job sync PDDIKTI: memuat integrasi di worker, menjalankan service per batch, memperbarui progress/status,
 * dan melepas lock pada semua jalur terminal.
 */
abstract class AbstractSyncPddiktiJob implements ShouldQueue
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
        public readonly ?int $requestedBy = null,
    ) {
        $this->onQueue('default');
    }

    abstract protected function service(): QueuedPddiktiSyncService;

    public function handle(): void
    {
        $service = $this->service();
        $progressKey = $service->progressPrefix() . $this->progressId;

        try {
            $integration = IamApiIntegration::findOrFail($this->integrationId);
            $result = $service->processPddikti($integration, $this->progressId);
            $this->updateProgress($progressKey, [
                'status' => 'completed',
                'phase' => 'completed',
                'progress' => 100,
                'processed' => $result['processed'] ?? 0,
                'total' => $result['processed'] ?? 0,
                'message' => "Sinkronisasi {$service->syncLabel()} PDDIKTI selesai.",
                'result' => $result,
                'finished_at' => now()->toISOString(),
            ]);
            $this->updateIntegrationStatus($integration, 'success', $service);
        } catch (Throwable $exception) {
            Log::error("Background sync {$service->syncLabel()} PDDIKTI gagal.", [
                'integration_id' => $this->integrationId,
                'exception' => $exception::class,
            ]);
            $this->updateProgress($progressKey, [
                'status' => 'failed',
                'phase' => 'failed',
                'progress' => null,
                'message' => $exception instanceof ApiIntegrationSyncException
                    ? $exception->getMessage()
                    : "Sinkronisasi {$service->syncLabel()} gagal. Periksa log aplikasi untuk detail teknis.",
                'finished_at' => now()->toISOString(),
            ]);
            $integration = IamApiIntegration::find($this->integrationId);
            if ($integration) {
                $this->updateIntegrationStatus($integration, 'failed', $service);
            }
        } finally {
            Cache::restoreLock($service->lockKey(), $this->lockOwner)->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $service = $this->service();
        $this->updateProgress($service->progressPrefix() . $this->progressId, [
            'status' => 'failed',
            'phase' => 'failed',
            'progress' => null,
            'message' => "Worker gagal menjalankan sinkronisasi {$service->syncLabel()} PDDIKTI. Periksa konfigurasi queue worker.",
            'finished_at' => now()->toISOString(),
        ]);
        $integration = IamApiIntegration::find($this->integrationId);
        if ($integration) {
            $this->updateIntegrationStatus($integration, 'failed', $service);
        }
        Cache::restoreLock($service->lockKey(), $this->lockOwner)->release();
    }

    private function updateProgress(string $key, array $updates): void
    {
        Cache::put($key, array_merge(Cache::get($key, []), $updates), now()->addHours(2));
    }

    private function updateIntegrationStatus(IamApiIntegration $integration, string $status, QueuedPddiktiSyncService $service): void
    {
        $oldValues = $integration->only(['last_synced_at', 'last_status']);
        $integration->update(['last_synced_at' => now(), 'last_status' => $status]);
        try {
            AuditLogService::record(
                module: 'IAM',
                action: 'sync_' . $status,
                tableName: 'iam_api_integrations',
                recordId: $integration->id,
                oldValues: $oldValues,
                newValues: $integration->getChanges(),
                actorId: $this->requestedBy,
            );
        } catch (Throwable) {
            Log::warning("Gagal mencatat audit perubahan status sync {$service->syncLabel()} PDDIKTI.", [
                'integration_id' => $integration->id,
            ]);
        }
    }
}
