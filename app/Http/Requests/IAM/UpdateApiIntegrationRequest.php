<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url_api' => 'nullable|url|max:1000',
            'token' => 'nullable|string|max:2000',
            'auth_type' => 'required|in:bearer,header_key',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'url_api.url' => 'Format URL API tidak valid.',
            'url_api.max' => 'URL API maksimal 1000 karakter.',
            'auth_type.in' => 'Tipe autentikasi harus berupa bearer atau header_key.',
            'is_active.required' => 'Status aktif wajib ditentukan.',
            'is_active.boolean' => 'Status aktif harus bernilai boolean.',
        ];
    }
}
