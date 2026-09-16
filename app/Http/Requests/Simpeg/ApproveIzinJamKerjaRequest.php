<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class ApproveIzinJamKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:disetujui,ditolak',
            'catatan_approval' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan approval wajib ditentukan.',
            'status.in' => 'Keputusan approval harus disetujui atau ditolak.',
        ];
    }
}
