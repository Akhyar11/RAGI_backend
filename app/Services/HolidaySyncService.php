<?php

namespace App\Services;

use App\Models\NationalHoliday;
use Carbon\Carbon;
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
     * Sinkronisasi dari API publik Hari Libur Indonesia
     */
    public function syncHolidays(int $year): bool
    {
        try {
            $response = Http::timeout(10)->get("https://dayoffapi.vercel.app/api?year={$year}");
            if (!$response->successful()) {
                return false;
            }

            $items = $response->json();
            if (!is_array($items)) {
                return false;
            }

            foreach ($items as $item) {
                if (!isset($item['tanggal']) || !isset($item['keterangan'])) {
                    continue;
                }

                NationalHoliday::updateOrCreate(
                    ['holiday_date' => $item['tanggal']],
                    [
                        'name' => $item['keterangan'],
                        'is_mass_leave' => (bool) ($item['is_cuti'] ?? false),
                    ]
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("Gagal sinkronisasi hari libur {$year}: " . $e->getMessage());
            return false;
        }
    }
}
