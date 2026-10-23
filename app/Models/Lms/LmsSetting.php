<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan global modul LMS.
 *
 * Disimpan pada tabel `core_system_settings` (tabel pengaturan sistem yang
 * sudah tersedia) dengan prefix key `lms_`. Nilai di-cache singkat supaya
 * tidak query database pada setiap request; cache di-invalidate manual
 * setelah pengaturan diubah.
 */
class LmsSetting extends Model
{
    protected $table = 'core_system_settings';

    protected $fillable = ['key', 'value', 'description'];

    /**
     * Key feature flag integrasi presensi (PresensiV2).
     */
    public const KEY_FITUR_PRESENSI = 'lms_fitur_presensi';

    /**
     * Batas jumlah soal per import XLSX bank soal.
     */
    public const KEY_MAX_SOAL_IMPORT = 'lms_max_soal_import';

    /**
     * Batas ukuran berkas materi/tugas (MB).
     */
    public const KEY_UPLOAD_MAX_SIZE_MB = 'lms_upload_max_size_mb';

    /**
     * Batas ukuran berkas video materi (MB).
     */
    public const KEY_VIDEO_MAX_SIZE_MB = 'lms_video_max_size_mb';

    /**
     * Durasi cache feature flag (detik).
     */
    public const CACHE_TTL = 120;

    /**
     * Cache key feature flag presensi.
     */
    public const CACHE_KEY_FITUR_PRESENSI = 'lms_fitur_presensi';

    /**
     * Apakah fitur presensi (termasuk integrasi PresensiV2) aktif.
     *
     * Default: aktif. Admin tetap boleh mengakses saat flag dimatikan
     * (untuk keperluan support), namun dosen & mahasiswa mendapat 403.
     */
    public static function fiturPresensiAktif(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY_FITUR_PRESENSI, self::CACHE_TTL, function () {
            $value = static::query()->where('key', self::KEY_FITUR_PRESENSI)->value('value');

            if ($value === null || $value === '') {
                return true;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        });
    }

    /**
     * Menghapus cache feature flag (dipanggil setelah perubahan pengaturan).
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_FITUR_PRESENSI);
    }
}
