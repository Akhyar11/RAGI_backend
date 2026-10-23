<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaBimbinganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'dosen_id' => 'required|exists:siakad_dosen,id',
            'tanggal' => 'required|date',
            'bab_pembahasan' => 'required|string|max:100',
            'uraian_konsultasi' => 'required|string|min:10',
            'arahan_revisi' => 'nullable|string',
            'berkas_draft' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ];
    }
}
