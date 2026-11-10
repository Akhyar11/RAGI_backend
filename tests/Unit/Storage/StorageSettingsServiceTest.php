<?php

namespace Tests\Unit\Storage;

use App\Models\SystemSetting;
use App\Services\Storage\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_applies_database_settings_and_forgets_cached_disks(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.public_disk' => 'public',
            'filesystems.private_disk' => 'public',
            'filesystems.disks.r2' => [
                'driver' => 's3',
                'key' => 'old-key',
                'secret' => 'old-secret',
                'region' => 'auto',
                'bucket' => 'old-bucket',
                'url' => null,
                'endpoint' => 'https://old.example.test',
                'use_path_style_endpoint' => true,
            ],
        ]);

        $cachedDisk = Storage::disk('r2');

        foreach ([
            'filesystem_disk' => 'r2',
            'filesystem_public_disk' => 'r2',
            'filesystem_private_disk' => 'r2-private',
            'r2_access_key_id' => 'database-key',
            'r2_secret_access_key' => 'database-secret',
            'r2_default_region' => 'auto',
            'r2_bucket' => 'database-bucket',
            'r2_private_bucket' => 'database-private-bucket',
            'r2_url' => 'https://cdn.example.test',
            'r2_private_url' => 'https://private.example.test',
            'r2_endpoint' => 'https://account.r2.cloudflarestorage.com',
            'r2_use_path_style_endpoint' => 'true',
        ] as $key => $value) {
            SystemSetting::set($key, $value);
        }

        app(StorageSettingsService::class)->refresh();

        $this->assertSame('r2', config('filesystems.default'));
        $this->assertSame('r2', config('filesystems.public_disk'));
        $this->assertSame('r2-private', config('filesystems.private_disk'));
        $this->assertSame('database-key', config('filesystems.disks.r2.key'));
        $this->assertSame('database-secret', config('filesystems.disks.r2.secret'));
        $this->assertSame('database-bucket', config('filesystems.disks.r2.bucket'));
        $this->assertSame('database-private-bucket', config('filesystems.disks.r2-private.bucket'));
        $this->assertSame('https://account.r2.cloudflarestorage.com', config('filesystems.disks.r2-private.endpoint'));
        $this->assertNotSame($cachedDisk, Storage::disk('r2'));
    }
}
