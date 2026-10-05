<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankSoalKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'mata_kuliah_id' => 'nullable|exists:siakad_mata_kuliah,id',
        ];
    }
}
