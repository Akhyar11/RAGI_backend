<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterSkalaGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_skala' => 'sometimes|required|string|max:150',
            'golongan' => 'nullable|string|max:50',
            'masa_kerja_min_tahun' => 'sometimes|required|integer|min:0',
            'masa_kerja_max_tahun' => 'sometimes|required|integer|gte:masa_kerja_min_tahun',
            'nominal_gaji' => 'sometimes|required|numeric|min:0',
            'keterangan' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
