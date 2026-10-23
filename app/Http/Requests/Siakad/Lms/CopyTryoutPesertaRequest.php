<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class CopyTryoutPesertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'source_quiz_id' => 'required|integer|exists:lms_quiz,id',
        ];
    }

    public function messages(): array
    {
        return [
            'source_quiz_id.required' => 'Tryout sumber wajib dipilih.',
            'source_quiz_id.exists' => 'Tryout sumber tidak ditemukan.',
        ];
    }
}
