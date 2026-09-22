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
            'tahun_akademik_id' => 'required|integer|exists:core_master_tahun_akademik,id',
            'program_studi_id' => 'required|integer|exists:core_master_program_studi,id',
            'kuota_total' => 'required|integer|min:1',
        ];
    }
}
