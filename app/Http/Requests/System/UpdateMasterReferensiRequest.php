<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterReferensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe' => 'sometimes|required|string|max:50',
            'modul' => 'sometimes|nullable|string|max:50',
            'kode' => 'sometimes|nullable|string|max:50',
            'nama' => 'sometimes|required|string|max:255',
            'urutan' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
