<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class TestSmtpSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'             => 'required|email',
            'mail_host'         => 'nullable|string',
            'mail_port'         => 'nullable|numeric',
            'mail_scheme'       => 'nullable|string',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name'    => 'nullable|string',
        ];
    }
}
