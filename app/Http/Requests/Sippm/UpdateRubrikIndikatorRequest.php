<?php

namespace App\Http\Requests\Sippm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRubrikIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe_reviewer' => 'sometimes|in:kaprodi,admin',
            'nama_indikator' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'bobot' => 'sometimes|numeric|min:0|max:100',
            'skor_minimal_default' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }
}
