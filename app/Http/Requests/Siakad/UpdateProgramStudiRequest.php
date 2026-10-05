<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Siakad\ProgramStudi;

class UpdateProgramStudiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prodiId = $this->route('id');
        return [
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'nama' => 'required|string|max:255',
            'prefix_nim' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique(ProgramStudi::class, 'prefix_nim')->ignore($prodiId)],
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
