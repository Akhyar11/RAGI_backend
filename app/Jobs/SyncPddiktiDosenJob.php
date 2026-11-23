<?php

namespace App\Jobs;

use App\Models\IamApiIntegration;
use App\Services\AuditLogService;
use App\Services\IAM\DosenIntegrationSyncService;
use App\Services\Siakad\NeoFeederSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPddiktiDosenJob implements ShouldQueue
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

    public function handle(NeoFeederSyncService $syncService): void
    {
        $progressKey = 'siakad-dosen-pddikti-sync:' . $this->progressId;
        try {
            $integration = IamApiIntegration::findOrFail($this->integrationId);
            $this->updateProgress(['status' => 'running', 'phase' => 'starting', 'message' => 'Worker mulai sinkronisasi dosen.']);

            $log = $syncService->pullBatchDosenFromFeeder($this->requestedBy, $this->progressId);
            $result = [
                'source_total' => $log->total_records,
                'synced' => $log->success_count,
                'failed' => $log->failed_count,
                'created' => $log->created_count ?? 0,
                'updated' => $log->updated_count ?? 0,
                'sso_created' => $log->sso_created_count ?? 0,
                'sso_updated' => $log->sso_updated_count ?? 0,
                'status' => $log->status,
                'sync_log_id' => $log->id,
            ];
            $queuedProgress = Cache::get($progressKey, []);
            $knownTotal = $queuedProgress['total'] ?? null;
            $this->updateProgress([
                'status' => $log->status === 'failed' ? 'failed' : 'completed',
                'phase' => $log->status === 'failed' ? 'failed' : 'completed',
                'progress' => $log->status === 'failed' ? null : 100,
                'processed' => $log->success_count + $log->failed_count,
                'total' => $knownTotal,
                'phase_processed' => $log->success_count + $log->failed_count,
                'phase_total' => $knownTotal,
                'work_processed' => $log->success_count + $log->failed_count,
                'work_total' => $knownTotal,
                'message' => $log->status === 'failed' ? 'Sinkronisasi dosen gagal.' : 'Sinkronisasi dosen selesai.',
                'result' => $result,
                'finished_at' => now()->toISOString(),
            ]);
            $this->updateIntegrationStatus($integration, $log->status === 'failed' ? 'failed' : 'success');
        } catch (Throwable $exception) {
            Log::error('Background sync dosen PDDIKTI gagal.', [
                'integration_id' => $this->integrationId,
                'exception' => $exception::class,
            ]);
            $this->updateProgress([
                'status' => 'failed',
                'phase' => 'failed',
                'message' => 'Sinkronisasi dosen gagal. Periksa log aplikasi untuk detail teknis.',
                'finished_at' => now()->toISOString(),
            ]);
            $integration = IamApiIntegration::find($this->integrationId);
            if ($integration) {
                $this->updateIntegrationStatus($integration, 'failed');
            }
        } finally {
            Cache::restoreLock('siakad-dosen-pddikti-sync:lock', $this->lockOwner)->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->updateProgress([
            'status' => 'failed',
            'phase' => 'failed',
            'message' => 'Worker gagal menjalankan sinkronisasi dosen. Periksa konfigurasi queue worker.',
            'finished_at' => now()->toISOString(),
        ]);
        $integration = IamApiIntegration::find($this->integrationId);
        if ($integration) {
            $this->updateIntegrationStatus($integration, 'failed');
        }
        Cache::restoreLock('siakad-dosen-pddikti-sync:lock', $this->lockOwner)->release();
    }

    private function updateProgress(array $updates): void
    {
        $key = 'siakad-dosen-pddikti-sync:' . $this->progressId;
        Cache::put($key, array_merge(Cache::get($key, []), $updates), now()->addHours(2));
    }

    private function updateIntegrationStatus(IamApiIntegration $integration, string $status): void
    {
        $oldValues = $integration->getOriginal();
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
        } catch (Throwable $auditException) {
            Log::warning('Gagal mencatat audit perubahan status sync dosen PDDIKTI.', [
                'integration_id' => $integration->id,
                'exception' => $auditException::class,
            ]);
        }
    }
}
