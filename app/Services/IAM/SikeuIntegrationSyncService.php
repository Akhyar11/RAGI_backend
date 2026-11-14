<?php

namespace App\Services\IAM;

use App\Models\IamApiIntegration;
use App\Services\Sikeu\SikeuMigratorService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SikeuIntegrationSyncService
{
    public function __construct(private SikeuMigratorService $migratorService) {}

    public function sync(IamApiIntegration $integration): array
    {
        if (!$integration->is_active) {
            throw new ApiIntegrationSyncException('Integrasi sedang dinonaktifkan.');
        }

        if (empty($integration->url_api)) {
            throw new ApiIntegrationSyncException('URL API belum dikonfigurasi di pengaturan integrasi.');
        }

        $type = match ($integration->key) {
            'sikeu_transaksi_kas'        => 'jurnal',
            'sikeu_transaksi_pembayaran' => 'pembayaran',
            default                      => throw new ApiIntegrationSyncException("Target sinkronisasi tidak valid untuk key '{$integration->key}'."),
        };

        $apiToken = method_exists($integration, 'getDecryptedToken')
            ? $integration->getDecryptedToken()
            : (filled($integration->token) ? (string) $integration->token : '');

        $options = [
            'mode'        => 'api',
            'api_url'     => $integration->url_api,
            'api_token'   => $apiToken,
            'auth_scheme' => $integration->auth_type ?? 'bearer',
            'type'        => $type,
            'source'      => 'api',
        ];

        try {
            $res = $this->migratorService->runMigration($options);

            $skippedItems = [];
            foreach ($res['skip_reasons'] ?? [] as $reason => $count) {
                if ($count > 0) {
                    $skippedItems[] = [
                        'source_id' => null,
                        'item_ref'  => null,
                        'row'       => $count,
                        'reason'    => "{$reason} ({$count} data)",
                    ];
                }
            }

            return [
                'source_total'  => $res['fetched'] ?? 0,
                'created'       => $res['created'] ?? 0,
                'updated'       => $res['updated'] ?? 0,
                'skipped'       => $res['skipped'] ?? 0,
                'skipped_items' => $skippedItems,
            ];
        } catch (Throwable $e) {
            Log::error('SIKEU integration sync error: ' . $e->getMessage(), [
                'integration_id' => $integration->id,
                'key'            => $integration->key,
            ]);

            throw new ApiIntegrationSyncException(
                'Sinkronisasi SIKEU gagal menghubungi layanan upstream atau terjadi kesalahan pemrosesan data.',
                502
            );
        }
    }
}
