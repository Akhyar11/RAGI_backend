<?php

namespace App\Services\IAM\Concerns;

use App\Models\IamApiIntegration;
use App\Services\IAM\ApiIntegrationSyncException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Antrean sync PDDIKTI: validasi, lock, progress "queued", dispatch setelah commit, respons ID progres.
 * Dipakai bersama dengan QueuedPddiktiSyncService; kelas pemakai wajib punya properti $neoFeeder.
 */
trait QueuesPddiktiSync
{
    /** @return class-string<\App\Jobs\AbstractSyncPddiktiJob> */
    abstract protected function jobClass(): string;

    public function lockKey(): string
    {
        return rtrim($this->progressPrefix(), ':') . ':lock';
    }

    /**
     * @return array{progress_id: string}
     */
    public function queuePddikti(IamApiIntegration $integration, ?int $requestedBy = null): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if (($integration->source_type ?? 'custom_api') !== 'pddikti') {
            throw new ApiIntegrationSyncException('Sinkronisasi antrean hanya untuk sumber PDDIKTI.');
        }
        if (config('queue.default') === 'sync') {
            throw new ApiIntegrationSyncException("Sinkronisasi {$this->syncLabel()} memerlukan queue worker. Atur QUEUE_CONNECTION ke database dan jalankan worker queue default.", 503);
        }

        $lock = Cache::lock($this->lockKey(), 3600);
        if (!$lock->get()) {
            throw new ApiIntegrationSyncException("Sinkronisasi {$this->syncLabel()} PDDIKTI sedang berjalan. Tunggu hingga selesai.", 409);
        }

        $progressId = (string) Str::uuid();
        $progressKey = $this->progressPrefix() . $progressId;
        Cache::put($progressKey, [
            'progress_id' => $progressId,
            'integration_id' => $integration->id,
            'requested_by' => $requestedBy,
            'status' => 'queued',
            'phase' => 'queued',
            'progress' => null,
            'processed' => 0,
            'total' => null,
            'message' => "Sinkronisasi {$this->syncLabel()} masuk antrean, menunggu worker.",
            'started_at' => now()->toISOString(),
            'result' => [],
        ], now()->addHours(2));

        try {
            $integration->update(['last_synced_at' => now(), 'last_status' => 'processing']);
            $job = $this->jobClass();
            $job::dispatch($integration->id, $progressId, $lock->owner(), $requestedBy)->afterCommit();
        } catch (\Throwable) {
            Cache::forget($progressKey);
            $lock->release();
            $integration->update(['last_synced_at' => now(), 'last_status' => 'failed']);
            throw new ApiIntegrationSyncException("Tidak dapat menjadwalkan sinkronisasi {$this->syncLabel()}. Periksa queue worker dan coba lagi.", 503);
        }

        return ['progress_id' => $progressId];
    }

    protected function updatePddiktiProgress(?string $progressId, array $updates): void
    {
        if ($progressId === null) {
            return;
        }
        $key = $this->progressPrefix() . $progressId;
        Cache::put($key, array_merge(Cache::get($key, []), $updates), now()->addHours(2));
    }

    /**
     * Total dari action count Neo Feeder yang terverifikasi (null bila tidak tersedia/ditolak/timeout: progress indeterminate).
     */
    protected function fetchPddiktiCount(string $action): ?int
    {
        try {
            $response = $this->neoFeeder->request($action, ['filter' => '']);
        } catch (\Throwable) {
            return null;
        }
        if ((int) ($response['error_code'] ?? -1) !== 0) {
            return null;
        }
        $value = $response['data'] ?? null;

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }

    /**
     * Progres persen hanya bila total terverifikasi; maksimum 99 sampai job menandai selesai.
     */
    protected function pddiktiProgressPercent(int $processed, ?int $total): ?int
    {
        if ($total === null || $total <= 0) {
            return null;
        }

        return min(99, (int) floor($processed / $total * 100));
    }
}
