<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\TarifSpmb;
use App\Models\Sikeu\MasterBiaya;

class SpmbSikeuService
{
    /**
     * Mengambil nominal tarif pendaftaran SPMB berdasarkan kombinasi jalur_id dan gelombang_id.
     * Menggunakan fallback ke nominal_standar pada master_biaya dengan tipe 'spmb_adm' jika tarif spesifik tidak ditemukan.
     *
     * @param int|string $jalurId
     * @param int|string $gelombangId
     * @return float
     */
    public function getTarifPendaftaranSpmb($jalurId, $gelombangId): float
    {
        $tarif = TarifSpmb::where('jalur_id', $jalurId)
            ->where('gelombang_id', $gelombangId)
            ->where('is_active', true)
            ->first();

        if ($tarif && $tarif->nominal > 0) {
            return (float) $tarif->nominal;
        }

        $masterBiaya = MasterBiaya::where('tipe', 'spmb_adm')->first() ?? MasterBiaya::first();
        if ($masterBiaya && $masterBiaya->nominal_standar > 0) {
            return (float) $masterBiaya->nominal_standar;
        }

        if ($gelombangId) {
            $gelombang = \App\Models\Spmb\GelombangPenerimaan::find($gelombangId);
            if ($gelombang && $gelombang->biaya_pendaftaran > 0) {
                return (float) $gelombang->biaya_pendaftaran;
            }
        }

        return 250000.00;
    }
}
