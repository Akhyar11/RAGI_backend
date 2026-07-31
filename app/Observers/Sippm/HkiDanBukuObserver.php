<?php

namespace App\Observers\Sippm;

use App\Models\Sippm\HkiDanBuku;
use App\Services\Sippm\SippmIntegrationService;

class HkiDanBukuObserver
{
    protected $integrationService;

    public function __construct(SippmIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Handle the HkiDanBuku "updated" event.
     */
    public function updated(HkiDanBuku $hkiDanBuku): void
    {
        // When LPPM verifies HKI/Book, auto-export to SIMPEG BKD
        if ($hkiDanBuku->wasChanged('is_verified_lppm') && $hkiDanBuku->is_verified_lppm) {
            $kum = match ($hkiDanBuku->jenis_luaran) {
                'paten' => 40.0,
                'buku_monograf' => 20.0,
                'buku_ajar' => 20.0,
                'hak_cipta' => 15.0,
                default => 10.0,
            };

            $this->integrationService->syncToSimpegBkd(
                $hkiDanBuku->pegawai,
                'hki_dan_buku',
                $hkiDanBuku->judul,
                $kum
            );
        }
    }
}
