<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRequestNomorSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:setujui,approve,tolak,reject',
            'catatan' => 'nullable|string',
            'kode_klasifikasi' => 'nullable|string|max:50',
            'kode_unit' => 'nullable|string|max:50',
            'perihal' => 'nullable|string|max:255',
            'tujuan' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan verifikasi (setujui / tolak) wajib dipilih.',
            'action.in' => 'Keputusan verifikasi tidak valid.',
        ];
    }
}
