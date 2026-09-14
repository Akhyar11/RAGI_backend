<?php

namespace App\Services;

use App\Models\NationalHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidaySyncService
{
    /**
     * Sinkronisasi data hari libur nasional jika data tahun ini belum ada.
     */
    public function syncIfNeeded(?int $year = null): bool
    {
        $targetYear = $year ?? Carbon::now()->year;

        $hasData = NationalHoliday::whereYear('holiday_date', $targetYear)->exists();
        if ($hasData) {
            return false;
        }

        return $this->syncHolidays($targetYear);
    }

    /**
     * Sinkronisasi dari API Libur Indonesia (Opica) dengan fallback ke API legacy.
     * Sumber utama: https://app.opica.id/api-libur/api?year={year}
     * Format: { status, count, data: [{ holiday_date, description, holiday_type }] }
     * holiday_type: "libur_nasional" | "cuti_bersama"
     *
     * Mengembalikan jumlah tanggal yang tersimpan (0 bila semua API gagal).
     */
    public function syncAndCount(int $year): int
    {
        try {
            $items = $this->fetchFromOpica($year);

            if (empty($items)) {
                $items = $this->fetchFromLegacy($year);
            }

            if (empty($items)) {
                return 0;
            }

            $count = 0;
            DB::transaction(function () use ($items, &$count) {
                foreach ($items as $item) {
                    $normalized = $this->normalizeItem($item);
                    if ($normalized === null) {
                        continue;
                    }

                    NationalHoliday::updateOrCreate(
                        ['holiday_date' => $normalized['holiday_date']],
                        [
                            'name' => $normalized['name'],
                            'is_mass_leave' => $normalized['is_mass_leave'],
                        ]
                    );
                    $count++;
                }
            });

            return $count;
        } catch (\Throwable $e) {
            Log::warning("Gagal sinkronisasi hari libur {$year}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sinkronisasi dari API publik Hari Libur Indonesia.
     */
    public function syncHolidays(int $year): bool
    {
        return $this->syncAndCount($year) > 0;
    }

    /**
     * Ambil data dari API Opica (sumber utama).
     * GET {base_url}/api?year={year}
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromOpica(int $year): array
    {
        try {
            $baseUrl = rtrim((string) config('services.holiday.base_url', 'https://app.opica.id/api-libur'), '/');
            $timeout = (int) config('services.holiday.timeout', 10);

            $response = Http::timeout($timeout)->acceptJson()->get("{$baseUrl}/api", ['year' => $year]);
            if (!$response->successful()) {
                return [];
            }

            $payload = $response->json();
            $items = is_array($payload) && array_key_exists('data', $payload) ? $payload['data'] : $payload;

            return is_array($items) ? array_values($items) : [];
        } catch (\Throwable $e) {
            Log::warning("Gagal fetch libur Opica {$year}: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Fallback ke API legacy dayoffapi bila Opica tidak dapat dijangkau.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromLegacy(int $year): array
    {
        try {
            $response = Http::timeout(10)->get("https://dayoffapi.vercel.app/api?year={$year}");
            if (!$response->successful()) {
                return [];
            }

            $items = $response->json();

            return is_array($items) ? array_values($items) : [];
        } catch (\Throwable $e) {
            Log::warning("Gagal fetch libur legacy {$year}: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Normalisasi satu item API ke format NationalHoliday.
     * Mendukung format Opica {holiday_date, description, holiday_type}
     * dan format legacy {tanggal, keterangan, is_cuti}.
     *
     * @param  array<string, mixed>  $item
     * @return array{holiday_date: string, name: string, is_mass_leave: bool}|null
     */
    private function normalizeItem(array $item): ?array
    {
        $date = $item['holiday_date'] ?? $item['tanggal'] ?? null;
        $name = $item['description'] ?? $item['keterangan'] ?? $item['name'] ?? null;

        if (!is_string($date) || !is_string($name) || trim($date) === '' || trim($name) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        if (array_key_exists('holiday_type', $item)) {
            $isMassLeave = strtolower((string) $item['holiday_type']) === 'cuti_bersama';
        } else {
            $isMassLeave = (bool) ($item['is_cuti'] ?? $item['is_mass_leave'] ?? false);
        }

        return [
            'holiday_date' => $date,
            'name' => trim($name),
            'is_mass_leave' => $isMassLeave,
        ];
    }
}
