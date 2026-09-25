<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreMataKuliahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kurikulum_id' => 'required|exists:siakad_kurikulum,id',
            'kode_mk' => 'required|string|max:20|unique:siakad_mata_kuliah,kode_mk',
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
