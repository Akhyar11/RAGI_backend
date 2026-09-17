<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class TestR2SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'r2_access_key_id'           => 'nullable|string',
            'r2_secret_access_key'       => 'nullable|string',
            'r2_endpoint'                => 'nullable|string',
            'r2_bucket'                  => 'nullable|string',
            'r2_default_region'          => 'nullable|string',
            'r2_use_path_style_endpoint' => 'nullable|string',
        ];
    }
}
