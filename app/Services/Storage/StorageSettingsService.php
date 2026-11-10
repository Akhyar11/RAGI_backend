<?php

namespace App\Services\Storage;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageSettingsService
{
    private const DISK_NAMES = ['r2', 'r2-private'];

    public function refresh(): void
    {
        try {
            $settings = SystemSetting::query()
                ->whereIn('key', [
                    'filesystem_disk',
                    'filesystem_public_disk',
                    'filesystem_private_disk',
                    'r2_access_key_id',
                    'r2_secret_access_key',
                    'r2_default_region',
                    'r2_bucket',
                    'r2_private_bucket',
                    'r2_url',
                    'r2_private_url',
                    'r2_endpoint',
                    'r2_use_path_style_endpoint',
                ])
                ->pluck('value', 'key')
                ->all();

            $value = static fn (string $key, mixed $fallback): mixed => array_key_exists($key, $settings)
                ? $settings[$key]
                : $fallback;

            $r2Config = config('filesystems.disks.r2', []);
            $privateConfig = config('filesystems.disks.r2-private', []);
            $region = $value('r2_default_region', $r2Config['region'] ?? 'auto');
            $endpoint = $value('r2_endpoint', $r2Config['endpoint'] ?? null);
            $key = $value('r2_access_key_id', $r2Config['key'] ?? null);
            $secret = $value('r2_secret_access_key', $r2Config['secret'] ?? null);
            $bucket = $value('r2_bucket', $r2Config['bucket'] ?? null);
            $url = $value('r2_url', $r2Config['url'] ?? null);
            $pathStyle = filter_var(
                $value('r2_use_path_style_endpoint', $r2Config['use_path_style_endpoint'] ?? true),
                FILTER_VALIDATE_BOOLEAN,
            );

            $r2Config = array_replace($r2Config, [
                'key' => $key,
                'secret' => $secret,
                'region' => $region ?: 'auto',
                'bucket' => $bucket,
                'url' => $url,
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => $pathStyle,
            ]);

            $privateConfig = array_replace($privateConfig, [
                'key' => $key,
                'secret' => $secret,
                'region' => $region ?: 'auto',
                'bucket' => $value('r2_private_bucket', $privateConfig['bucket'] ?? $bucket),
                'url' => $value('r2_private_url', $privateConfig['url'] ?? null),
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => $pathStyle,
            ]);

            config([
                'filesystems.default' => $value('filesystem_disk', config('filesystems.default', 'local')),
                'filesystems.public_disk' => $value('filesystem_public_disk', config('filesystems.public_disk', 'public')),
                'filesystems.private_disk' => $value('filesystem_private_disk', config('filesystems.private_disk', config('filesystems.public_disk', 'public'))),
                'filesystems.disks.r2' => $r2Config,
                'filesystems.disks.r2-private' => $privateConfig,
            ]);

            foreach (self::DISK_NAMES as $disk) {
                Storage::forgetDisk($disk);
            }
        } catch (Throwable $exception) {
            Log::warning('Gagal memuat ulang konfigurasi storage dari database.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
