<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterReferensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe' => 'required|string|max:50',
            'modul' => 'nullable|string|max:50',
            'kode' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
