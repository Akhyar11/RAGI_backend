<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Services\Siakad\SiakadDosenSyncException;
use App\Services\Siakad\SiakadDosenSyncService;
use Illuminate\Support\Facades\Log;

class SiakadDosenIntegrationSyncService
{
    public function __construct(private SiakadDosenSyncService $syncService) {}

    public function sync(IamApiIntegration $integration, array $options = []): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }

        if (empty($integration->url_api)) {
            throw new ApiIntegrationSyncException('URL API belum dikonfigurasi di pengaturan integrasi.');
        }

        if ($integration->key !== 'siakad_dosen') {
            throw new ApiIntegrationSyncException("Aksi sync belum didukung untuk key '{$integration->key}'.");
        }

        $apiToken = method_exists($integration, 'getDecryptedToken')
            ? $integration->getDecryptedToken()
            : (filled($integration->token) ? (string) $integration->token : '');

        if ($apiToken === '') {
            throw new ApiIntegrationSyncException('Token API belum dikonfigurasi di pengaturan integrasi.');
        }

        $params = array_merge($options, [
            'url' => $integration->url_api,
            'token' => $apiToken,
            'auth_scheme' => $integration->auth_type ?? 'bearer',
        ]);

        try {
            $res = $this->syncService->sync($params);

            $skippedItems = [];
            foreach ($res['skip_reasons'] ?? [] as $reason => $count) {
                if ($count > 0) {
                    $skippedItems[] = [
                        'reason' => $reason,
                        'count' => $count,
                    ];
                }
            }

            return [
                'source_total' => $res['fetched'] ?? 0,
                'created' => $res['created'] ?? 0,
                'updated' => $res['updated'] ?? 0,
                'skipped' => $res['skipped'] ?? 0,
                'failed' => $res['failed'] ?? 0,
                'pages' => $res['pages'] ?? 0,
                'penugasan_created' => $res['penugasan_created'] ?? 0,
                'penugasan_homebase' => $res['penugasan_homebase'] ?? 0,
                'tahun_akademik_id' => $res['tahun_akademik_id'] ?? null,
                'skip_reasons' => $res['skip_reasons'] ?? [],
                'skipped_items' => $skippedItems,
                'errors' => $res['errors'] ?? [],
            ];
        } catch (SiakadDosenSyncException $e) {
            throw new ApiIntegrationSyncException($e->getMessage(), 502);
        } catch (\Throwable $e) {
            Log::error('Sinkronisasi dosen siakad via IAM gagal: ' . $e->getMessage(), [
                'integration_id' => $integration->id,
                'key' => $integration->key,
            ]);

            throw new ApiIntegrationSyncException(
                'Sinkronisasi data dosen dari SIAKAD gagal. Periksa koneksi/API sumber dan coba lagi.',
                502
            );
        }
    }
}
