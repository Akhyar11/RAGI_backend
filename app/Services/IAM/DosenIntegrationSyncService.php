<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Services\Siakad\NeoFeederService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SyncPddiktiDosenJob;

class DosenIntegrationSyncService
{
    public function __construct(
        private NeoFeederService $neoFeeder,
    ) {}

    public function testPddiktiConnection(string $action): array
    {
        if ($action !== 'GetListDosen') {
            throw new ApiIntegrationSyncException('Action tes koneksi Dosen PDDIKTI tidak valid.');
        }

        try {
            $response = $this->neoFeeder->request($action, [
                'order' => 'nama_dosen',
                'limit' => 1,
                'offset' => 0,
            ]);
        } catch (\Throwable) {
            throw new ApiIntegrationSyncException('Koneksi Neo Feeder gagal. Periksa konfigurasi dan hak akses GetListDosen.', 502);
        }

        if ((int) ($response['error_code'] ?? -1) !== 0) {
            throw new ApiIntegrationSyncException('Neo Feeder menolak permintaan GetListDosen. Periksa hak akses akun Feeder.', 502);
        }

        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            throw new ApiIntegrationSyncException('Respons GetListDosen dari Neo Feeder tidak valid.', 502);
        }
        if ($data !== [] && !array_is_list($data)) {
            $data = [$data];
        }

        return [
            'action' => $action,
            'data_count' => count($data),
            'sample_fields' => isset($data[0]) && is_array($data[0]) ? array_keys($data[0]) : [],
        ];
    }

    public function sync(IamApiIntegration $integration, ?int $userId = null): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }
        if ($integration->key !== 'siakad_dosen'
            || ($integration->source_type ?? 'custom_api') !== 'pddikti'
            || !in_array('pddikti', $integration->supported_sources ?? [], true)
            || ($integration->source_schemas['pddikti']['target_entity'] ?? null) !== 'dosen') {
            throw new ApiIntegrationSyncException('Pilih sumber PDDIKTI untuk sinkronisasi Dosen.');
        }

        if (config('queue.default') === 'sync') {
            throw new ApiIntegrationSyncException('Sinkronisasi progresif dosen memerlukan queue worker. Atur QUEUE_CONNECTION ke database dan jalankan worker queue default.', 503);
        }

        $lock = Cache::lock('siakad-dosen-pddikti-sync:lock', 3600);
        if (!$lock->get()) {
            throw new ApiIntegrationSyncException('Sinkronisasi dosen PDDIKTI sedang berjalan. Tunggu hingga selesai.', 409);
        }

        $progressId = (string) Str::uuid();
        $progressKey = 'siakad-dosen-pddikti-sync:' . $progressId;
        Cache::put($progressKey, [
            'progress_id' => $progressId,
            'integration_id' => $integration->id,
            'requested_by' => $userId,
            'status' => 'queued',
            'phase' => 'queued',
                'progress' => null,
            'processed' => 0,
            'total' => null,
            'phase_processed' => 0,
            'phase_total' => null,
            'work_processed' => 0,
            'work_total' => null,
            'message' => 'Sinkronisasi dosen masuk antrean, menunggu worker.',
            'started_at' => now()->toISOString(),
            'result' => [
                'source_total' => 0,
                'synced' => 0,
                'failed' => 0,
                'status' => 'queued',
                'sync_log_id' => null,
            ],
        ], now()->addHours(2));

        try {
            $integration->update(['last_synced_at' => now(), 'last_status' => 'processing']);
            SyncPddiktiDosenJob::dispatch($integration->id, $progressId, $lock->owner(), $userId)->afterCommit();
        } catch (\Throwable $exception) {
            Cache::forget($progressKey);
            $lock->release();
            $integration->update(['last_synced_at' => now(), 'last_status' => 'failed']);
            throw new ApiIntegrationSyncException('Tidak dapat menjadwalkan sinkronisasi dosen. Periksa queue worker dan coba lagi.', 503);
        }

        return ['progress_id' => $progressId];
    }
}
