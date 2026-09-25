<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class BeriNilaiTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'nilai'          => 'required|numeric|min:0',
            'feedback_dosen' => 'nullable|string',
        ];
    }
}
