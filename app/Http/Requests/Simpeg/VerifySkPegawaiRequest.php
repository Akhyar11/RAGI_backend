<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class VerifySkPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_verifikasi' => 'required|in:terverifikasi,ditolak',
            'catatan_verifikasi' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status_verifikasi.required' => 'Status verifikasi wajib ditentukan.',
            'status_verifikasi.in' => 'Status verifikasi harus terverifikasi atau ditolak.',
        ];
    }
}
