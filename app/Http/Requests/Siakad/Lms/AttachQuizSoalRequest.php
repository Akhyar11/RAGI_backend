<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class AttachQuizSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'bank_soal_id' => 'required|exists:siakad_bank_soal,id',
            'urutan' => 'nullable|integer|min:0',
            'poin' => 'nullable|numeric|min:0|max:1000',
        ];
    }
}
