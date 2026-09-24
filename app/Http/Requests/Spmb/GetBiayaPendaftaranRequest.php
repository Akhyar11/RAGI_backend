<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class GetBiayaPendaftaranRequest extends FormRequest
{
    /**
     * Endpoint ini bersifat publik (dibaca saat pengisian formulir pendaftaran).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gelombang_id' => 'required|exists:spmb_gelombang_penerimaan,id',
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
        ];
    }
}
