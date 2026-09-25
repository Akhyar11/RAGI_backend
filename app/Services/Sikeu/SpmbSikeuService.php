<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\TarifSpmb;
use App\Models\Spmb\GelombangPenerimaan;

class SpmbSikeuService
{
    /**
     * Mengambil nominal tarif pendaftaran SPMB berdasarkan kombinasi jalur_id dan gelombang_id.
     * Menggunakan fallback ke nominal_standar pada master_biaya dengan tipe 'spmb_adm' jika tarif spesifik tidak ditemukan.
     *
     * @param  int|string  $jalurId
     * @param  int|string  $gelombangId
     */
    public function getTarifPendaftaranSpmb($jalurId, $gelombangId): float
    {
        if ($gelombangId) {
            $gelombang = GelombangPenerimaan::find($gelombangId);
            if ($gelombang && (float) $gelombang->biaya_pendaftaran > 0) {
                return (float) $gelombang->biaya_pendaftaran;
            }
        }

        $masterBiaya = MasterBiaya::where('is_active', true)
            ->where(function ($q) {
                $q->where('kode', 'SPMB_ADM')->orWhere('tipe', 'spmb_adm');
            })
            ->first();

        if ($masterBiaya && $masterBiaya->nominal_standar > 0) {
            return (float) $masterBiaya->nominal_standar;
        }

        $tarif = TarifSpmb::where('jalur_id', $jalurId)
            ->where('gelombang_id', $gelombangId)
            ->where('is_active', true)
            ->first();

        if ($tarif && $tarif->nominal > 0) {
            return (float) $tarif->nominal;
        }

        return 0.0;
    }
}
