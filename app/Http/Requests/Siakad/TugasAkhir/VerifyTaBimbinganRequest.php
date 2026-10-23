<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTaBimbinganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status_validasi' => 'required|in:acc,revisi,menunggu',
            'arahan_revisi' => 'nullable|string',
        ];
    }
}
