<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMataKuliahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'sks_teori' => 'required|integer|min:0|max:12',
            'sks_praktik' => 'required|integer|min:0|max:12',
            'semester_anjuran' => 'required|integer|min:1|max:14',
            'tipe' => ['required', 'string', new MasterReferensiExists('tipe_mk')],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.required' => 'Tipe mata kuliah wajib dipilih.',
        ];
    }
}
