<?php

namespace App\Observers\Sippm;

use App\Models\Sippm\PublikasiIlmiah;
use App\Services\Sippm\SippmIntegrationService;

class PublikasiIlmiahObserver
{
    protected $integrationService;

    public function __construct(SippmIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Handle the PublikasiIlmiah "updated" event.
     */
    public function updated(PublikasiIlmiah $publikasi): void
    {
        // When LPPM verifies a publication, auto-export to SIMPEG BKD
        if ($publikasi->wasChanged('is_verified_lppm') && $publikasi->is_verified_lppm) {
            $kum = match ($publikasi->indexing) {
                'scopus_q1' => 40.0,
                'scopus_q2' => 35.0,
                'scopus_q3' => 30.0,
                'scopus_q4' => 25.0,
                'sinta_1', 'sinta_2' => 20.0,
                'sinta_3', 'sinta_4' => 15.0,
                default => 10.0,
            };

            $this->integrationService->syncToSimpegBkd(
                $publikasi->pegawai,
                'publikasi_ilmiah',
                $publikasi->judul_artikel,
                $kum
            );
        }
    }
}
