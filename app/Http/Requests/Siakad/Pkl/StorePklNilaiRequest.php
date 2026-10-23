<?php

namespace App\Http\Requests\Siakad\Pkl;

use Illuminate\Foundation\Http\FormRequest;

class StorePklNilaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.pkl.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nilai_lapangan_kedisiplinan' => 'nullable|numeric|min:0|max:100',
            'nilai_lapangan_kinerja' => 'nullable|numeric|min:0|max:100',
            'nilai_lapangan_kemampuan_teknis' => 'nullable|numeric|min:0|max:100',
            'nilai_lapangan_inisiatif' => 'nullable|numeric|min:0|max:100',
            'nilai_lapangan_total' => 'nullable|numeric|min:0|max:100',
            'catatan_lapangan' => 'nullable|string',
            'berkas_nilai_lapangan' => 'nullable|file|mimes:pdf|max:5120',
            'nilai_dosen_bimbingan' => 'nullable|numeric|min:0|max:100',
            'nilai_dosen_laporan' => 'nullable|numeric|min:0|max:100',
            'nilai_dosen_presentasi' => 'nullable|numeric|min:0|max:100',
            'nilai_dosen_total' => 'nullable|numeric|min:0|max:100',
            'catatan_dosen' => 'nullable|string',
            'bobot_lapangan_persen' => 'nullable|numeric|min:0|max:100',
            'bobot_dosen_persen' => 'nullable|numeric|min:0|max:100',
            'is_finalized' => 'nullable|boolean',
        ];
    }
}
