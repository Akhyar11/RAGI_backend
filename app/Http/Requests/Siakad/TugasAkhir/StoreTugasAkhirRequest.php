<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTugasAkhirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'judul_utama' => 'required|string|min:10|max:255',
            'judul_inggris' => 'nullable|string|max:255',
            'bidang_kajian' => 'nullable|string|max:100',
            'abstrak' => 'nullable|string',
            'usulan_pembimbing_1_id' => 'nullable|exists:siakad_dosen,id',
            'usulan_pembimbing_2_id' => 'nullable|exists:siakad_dosen,id',
            'berkas_proposal' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }
}
