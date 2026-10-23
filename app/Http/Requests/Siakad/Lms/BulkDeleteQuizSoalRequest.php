<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteQuizSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'quiz_soal_ids' => 'required|array|min:1',
            'quiz_soal_ids.*' => 'required|integer|distinct|exists:lms_quiz_soal,id',
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_soal_ids.required' => 'Pilih minimal satu soal untuk dilepas.',
            'quiz_soal_ids.*.exists' => 'Salah satu soal tidak ditemukan pada tryout ini.',
        ];
    }
}
