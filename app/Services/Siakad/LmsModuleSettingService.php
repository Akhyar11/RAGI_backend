<?php

namespace App\Services\Siakad;

use App\Models\Lms\LmsSetting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan level modul LMS (bukan per kelas).
 *
 * Nilai disimpan pada tabel `core_system_settings` dengan prefix key `lms_`
 * (tabel pengaturan sistem yang sudah ada; tidak ada tabel baru). Definisi
 * key, tipe, batas nilai, dan default berada di SATU tempat
 * (`definitions()`) agar validasi FormRequest, endpoint publik, dan UI
 * selalu sinkron.
 */
class LmsModuleSettingService
{
    public const CACHE_KEY = 'lms_module_settings';

    public const CACHE_KEY_PUBLIC = 'lms_public_settings';

    public const CACHE_TTL = 120;

    public const CACHE_TTL_PUBLIC = 86400;

    /**
     * Definisi pengaturan modul LMS.
     *
     * `public` = boleh dibaca tanpa autentikasi lewat `GET /lms/public/settings`.
     * Hanya berisi nilai non-sensitif yang dibutuhkan UI (batas upload, dsb).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            LmsSetting::KEY_FITUR_PRESENSI => [
                'label' => 'Fitur Presensi',
                'group' => 'fitur',
                'type' => 'boolean',
                'default' => true,
                'public' => true,
                'description' => 'Mengaktifkan presensi & integrasi PresensiV2 untuk dosen/mahasiswa. Admin tetap bisa mengakses panel integrasi saat nonaktif.',
            ],
            LmsSetting::KEY_MAX_SOAL_IMPORT => [
                'label' => 'Maksimal Soal per Import',
                'group' => 'bank_soal',
                'type' => 'integer',
                'min' => 1,
                'max' => 1000,
                'default' => 500,
                'public' => false,
                'description' => 'Batas jumlah baris soal yang diproses dalam satu kali import XLSX bank soal.',
            ],
            LmsSetting::KEY_UPLOAD_MAX_SIZE_MB => [
                'label' => 'Batas Ukuran Berkas Materi (MB)',
                'group' => 'berkas',
                'type' => 'integer',
                'min' => 1,
                'max' => 200,
                'default' => 100,
                'public' => true,
                'description' => 'Ukuran maksimum berkas materi/tugas non-video yang dapat diunggah.',
            ],
            LmsSetting::KEY_VIDEO_MAX_SIZE_MB => [
                'label' => 'Batas Ukuran Video (MB)',
                'group' => 'berkas',
                'type' => 'integer',
                'min' => 1,
                'max' => 2048,
                'default' => 500,
                'public' => true,
                'description' => 'Ukuran maksimum berkas video materi yang dapat diunggah.',
            ],
        ];
    }

    /**
     * Seluruh nilai pengaturan modul (default dipakai bila belum tersimpan).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $definitions = self::definitions();
            $stored = LmsSetting::query()
                ->whereIn('key', array_keys($definitions))
                ->pluck('value', 'key');

            $result = [];

            foreach ($definitions as $key => $definition) {
                $value = $stored[$key] ?? null;
                $result[$key] = ($value === null || $value === '')
                    ? $definition['default']
                    : $this->castValue($definition, $value);
            }

            return $result;
        });
    }

    /**
     * Pengaturan yang boleh dibaca publik (cache 24 jam).
     *
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        return Cache::remember(self::CACHE_KEY_PUBLIC, self::CACHE_TTL_PUBLIC, function () {
            $keys = array_keys(array_filter(
                self::definitions(),
                fn (array $definition) => (bool) ($definition['public'] ?? false)
            ));

            return array_intersect_key($this->all(), array_flip($keys));
        });
    }

    /**
     * Simpan sebagian/seluruh pengaturan modul.
     *
     * Hanya key yang didefinisikan yang diproses. Perubahan dicatat ke
     * `core_audit_logs` (module LMS) dalam try/catch agar kegagalan audit
     * tidak membatalkan penyimpanan.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>  Nilai terbaru setelah disimpan
     */
    public function update(array $values, ?int $actorId = null): array
    {
        $definitions = self::definitions();
        $oldValues = $this->all();
        $changed = [];

        DB::transaction(function () use ($values, $definitions, &$changed) {
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $definitions)) {
                    continue;
                }

                $definition = $definitions[$key];
                $normalized = $this->castValue($definition, $value);

                LmsSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $this->serializeValue($definition, $normalized),
                        'description' => $definition['description'],
                    ]
                );

                $changed[$key] = $normalized;
            }
        });

        $this->flushCache();

        $newValues = $this->all();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'core_system_settings',
                recordId: null,
                oldValues: array_intersect_key($oldValues, $changed),
                newValues: $changed
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log pengaturan LMS: ' . $e->getMessage());
        }

        return $newValues;
    }

    /**
     * Bersihkan cache pengaturan modul + cache feature flag presensi.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_PUBLIC);
        Cache::forget(LmsSetting::CACHE_KEY_FITUR_PRESENSI);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function castValue(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function serializeValue(array $definition, mixed $value): string
    {
        return match ($definition['type']) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
