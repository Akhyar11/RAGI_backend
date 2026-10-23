<?php

namespace App\Http\Requests\Siakad\Lms\Concerns;

use App\Models\Lms\LmsSetting;
use App\Services\Siakad\LmsModuleSettingService;

/**
 * Menentukan batas ukuran berkas materi berdasarkan pengaturan modul LMS.
 *
 * Batas dibedakan menurut tipe berkas: video memakai `lms_video_max_size_mb`,
 * selain video memakai `lms_upload_max_size_mb`. Nilai selalu diambil dari
 * `LmsModuleSettingService` agar perubahan pengaturan langsung berlaku tanpa
 * mengubah kode validasi.
 */
trait ResolvesMateriUploadLimit
{
    /**
     * Batas ukuran berkas dalam kilobyte (satuan yang dipakai rule `max:`
     * Laravel).
     */
    protected function materiMaxSizeKb(): int
    {
        $key = $this->resolveMateriLimitKey();

        return max(1, (int) (app(LmsModuleSettingService::class)->all()[$key] ?? 1)) * 1024;
    }

    /**
     * Pesan validasi ukuran berkas yang menyebut batas efektif.
     */
    protected function materiMaxSizeMessage(): string
    {
        $key = $this->resolveMateriLimitKey();
        $maxMb = max(1, (int) (app(LmsModuleSettingService::class)->all()[$key] ?? 1));

        return "Ukuran berkas melebihi batas {$maxMb} MB sesuai pengaturan modul LMS.";
    }

    /**
     * Tentukan key pengaturan yang berlaku untuk berkas yang diunggah.
     */
    private function resolveMateriLimitKey(): string
    {
        $file = $this->file('file');

        $isVideo = false;

        if ($file) {
            try {
                $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            } catch (\Throwable) {
                // Berkas tidak valid; biarkan rule `file` yang melaporkan.
                $isVideo = false;
            }
        }

        return $isVideo
            ? LmsSetting::KEY_VIDEO_MAX_SIZE_MB
            : LmsSetting::KEY_UPLOAD_MAX_SIZE_MB;
    }
}
