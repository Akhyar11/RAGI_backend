<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Registrasi akun bersifat publik.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|unique:core_users',
            'email' => 'required|string|email|unique:core_users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'referral_code' => 'nullable|string|max:50',
        ];
    }
}
