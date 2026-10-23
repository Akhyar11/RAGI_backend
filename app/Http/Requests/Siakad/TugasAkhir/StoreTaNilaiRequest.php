<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaNilaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'skor_metodologi' => 'required|numeric|min:0|max:100',
            'skor_materi_skripsi' => 'required|numeric|min:0|max:100',
            'skor_presentasi' => 'required|numeric|min:0|max:100',
            'skor_penguasaan_materi' => 'required|numeric|min:0|max:100',
            'catatan_revisi' => 'nullable|string',
        ];
    }
}
