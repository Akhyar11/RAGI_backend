<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * Satu sumber kebenaran untuk parameter presensi yang dipakai bersama
 * oleh web admin, mesin verifikasi wajah, dan aplikasi mobile.
 */
class PresensiSettingService
{
    public const FACE_SCORE_THRESHOLD_KEY = 'face_score_threshold';

    public const FALLBACK_FACE_SCORE_THRESHOLD = 0.68;

    /**
     * Ambil pengaturan kemiripan wajah yang aktif.
     *
     * Prioritas: nilai yang disimpan admin di core_system_settings, lalu
     * konfigurasi environment sebagai fallback saat setting belum ada.
     */
    public function getFaceSimilaritySettings(): array
    {
        $setting = SystemSetting::where('key', self::FACE_SCORE_THRESHOLD_KEY)->first();
        $fallback = (float) config(
            'services.face_service.min_similarity',
            self::FALLBACK_FACE_SCORE_THRESHOLD
        );

        $value = $fallback;
        $source = 'environment';

        if ($setting && $setting->value !== null && $setting->value !== '') {
            $saved = (float) $setting->value;
            if ($saved > 0) {
                $value = $saved;
                $source = 'database';
            }
        }

        if ($value <= 0) {
            $value = self::FALLBACK_FACE_SCORE_THRESHOLD;
        }

        return [
            'face_score_threshold' => $value,
            'minimum_face_similarity' => $value,
            'source' => $source,
            'updated_at' => $source === 'database' && $setting?->updated_at
                ? $setting->updated_at->toIso8601String()
                : null,
        ];
    }

    /**
     * Ambil batas kemiripan wajah dalam bentuk angka saja.
     */
    public function getFaceScoreThreshold(): float
    {
        return (float) $this->getFaceSimilaritySettings()['face_score_threshold'];
    }
}
