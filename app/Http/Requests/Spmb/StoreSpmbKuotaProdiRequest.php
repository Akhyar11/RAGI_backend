<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpmbKuotaProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_akademik_id' => 'required|integer|exists:siakad_tahun_akademik,id',
            'program_studi_id' => 'required|integer|exists:siakad_program_studi,id',
            'kuota_total' => 'required|integer|min:1',
        ];
    }
}
