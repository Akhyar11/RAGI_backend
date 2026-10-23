<?php

namespace App\Http\Requests\Siakad\Pkl;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPklBimbinganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.pkl.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status_verifikasi' => 'required|in:disetujui,revisi',
            'catatan_dosen' => 'nullable|string',
        ];
    }
}
