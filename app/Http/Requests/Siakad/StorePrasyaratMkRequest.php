<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StorePrasyaratMkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'prasyarat_id' => 'required|exists:siakad_mata_kuliah,id|different:mata_kuliah_id',
            'tipe' => ['required', 'string', new MasterReferensiExists('tipe_prasyarat_mk')],
            'nilai_minimum' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.required' => 'Kriteria prasyarat wajib dipilih.',
            'prasyarat_id.different' => 'Mata kuliah prasyarat tidak boleh sama dengan mata kuliah utama.',
        ];
    }
}
