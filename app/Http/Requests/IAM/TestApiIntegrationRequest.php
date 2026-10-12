<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class TestApiIntegrationRequest extends FormRequest
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
            'auth_type' => 'nullable|in:bearer,header_key',
        ];
    }

    public function messages(): array
    {
        return [
            'url_api.url' => 'Format URL API tidak valid.',
            'auth_type.in' => 'Tipe autentikasi harus berupa bearer atau header_key.',
        ];
    }
}
