<?php

namespace Tests\Feature;

use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStreamSignedTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_relative_allows_access_to_private_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $fileService = app(FileStorageService::class);
        $file = UploadedFile::fake()->create('dokumen_rahasia.pdf', 100, 'application/pdf');
        $storedPath = $fileService->store($file, 'simpeg/cuti_lampiran');

        $signedUrl = $fileService->signedUrl($storedPath);
        $this->assertNotNull($signedUrl);
        $this->assertStringContainsString('/api/files/view?', $signedUrl);

        // Akses dengan Signed URL harus berhasil (200 OK)
        $response = $this->get($signedUrl);
        $response->assertStatus(200);

        // Akses tanpa signature harus ditolak (403 Forbidden - Invalid signature)
        $unsignedUrl = '/api/files/view?path=' . urlencode($storedPath);
        $forbiddenResponse = $this->get($unsignedUrl);
        $forbiddenResponse->assertStatus(403);
    }
}
