<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramStudiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'nama' => 'required|string|max:255',
            'kode_prodi_dikti' => 'nullable|string|max:50',
            'jenjang' => ['required', 'string', 'max:20', new MasterReferensiExists('jenjang_prodi')],
            'akreditasi' => ['nullable', 'string', 'max:50', new MasterReferensiExists('akreditasi_prodi')],
        ];
    }

    public function messages(): array
    {
        return [
            'jenjang.required' => 'Jenjang pendidikan wajib dipilih.',
        ];
    }
}
