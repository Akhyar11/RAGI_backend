<?php

namespace App\Http\Requests\Sippm;

use Illuminate\Foundation\Http\FormRequest;

class StoreRubrikIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe_reviewer' => 'required|in:kaprodi,admin',
            'nama_indikator' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'bobot' => 'required|numeric|min:0|max:100',
            'skor_minimal_default' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }
}
