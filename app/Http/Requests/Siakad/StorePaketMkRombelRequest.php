<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class StorePaketMkRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.kelas.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'master_kelas_id' => 'required|exists:siakad_master_kelas,id',
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'semester_anjuran' => 'nullable|integer|min:1|max:14',
            'is_wajib' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'master_kelas_id.required' => 'Rombel wajib dipilih.',
            'mata_kuliah_id.required' => 'Mata kuliah wajib dipilih.',
        ];
    }
}
