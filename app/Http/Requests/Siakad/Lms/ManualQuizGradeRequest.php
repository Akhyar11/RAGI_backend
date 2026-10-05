<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class ManualQuizGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'poin' => 'required|numeric|min:0',
        ];
    }
}
