<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class StoreTahunAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('siakad.master.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => 'required|string|unique:siakad_tahun_akademik,kode',
            'nama' => 'required|string|max:255',
            'tahun_mulai' => 'nullable|integer',
            'tahun_selesai' => 'nullable|integer',
            'is_active' => 'boolean',
            'mode_penilaian' => 'nullable|string|max:20',
            'krs_mulai' => 'nullable|date',
            'krs_selesai' => 'nullable|date|after_or_equal:krs_mulai',
            'kprs_mulai' => 'nullable|date',
            'kprs_selesai' => 'nullable|date|after_or_equal:kprs_mulai',
            'perkuliahan_mulai' => 'nullable|date',
            'perkuliahan_selesai' => 'nullable|date|after_or_equal:perkuliahan_mulai',
            'input_nilai_mulai' => 'nullable|date',
            'input_nilai_selesai' => 'nullable|date|after_or_equal:input_nilai_mulai',
        ];
    }
}
