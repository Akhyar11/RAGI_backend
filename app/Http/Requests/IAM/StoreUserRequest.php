<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dihandle oleh Policy / ensureAdmin
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('password') && !$this->has('password_confirmation')) {
            $this->merge([
                'password_confirmation' => $this->password,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|unique:core_users,username',
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|unique:core_users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:core_roles,id',
        ];
    }
}
