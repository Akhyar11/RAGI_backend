<?php

namespace App\Services\Sikeu;

use Illuminate\Validation\ValidationException;

class SpmbSikeuService
{
    /**
    * Tarif pendaftaran tidak lagi dikelola di SIKEU atau gelombang.
    * Biaya SPMB harus berasal dari komponen Master Biaya SPMB.
     *
     * @param  int|string  $jalurId
     * @param  int|string  $gelombangId
     */
    public function getTarifPendaftaranSpmb($jalurId, $gelombangId): float
    {
        throw ValidationException::withMessages([
            'biaya' => 'Tarif SPMB tidak lagi dikelola di SIKEU. Atur komponen biaya melalui Master Biaya SPMB.',
        ]);
    }
}
