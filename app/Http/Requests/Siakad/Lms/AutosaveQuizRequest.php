<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class AutosaveQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('siakad.kelas.read') || $this->user()?->hasPermission('siakad.krs.read'));
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array|min:1',
            'answers.*.quiz_soal_id' => 'required|integer|exists:lms_quiz_soal,id',
            'answers.*.bank_opsi_id' => 'nullable|integer|exists:siakad_bank_soal_opsi,id',
            'answers.*.jawaban_teks' => 'nullable|string|max:5000',
        ];
    }
}
