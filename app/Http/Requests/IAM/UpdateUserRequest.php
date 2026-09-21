<?php

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dihandle oleh Policy / ensureAdmin
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('id');

        return [
            'username' => 'sometimes|string|unique:core_users,username,' . $userId,
            'name' => 'nullable|string|max:255',
            'email' => 'sometimes|string|email|unique:core_users,email,' . $userId,
            'password' => 'sometimes|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:core_roles,id',
        ];
    }
}
