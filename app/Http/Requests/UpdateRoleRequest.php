<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dihandle oleh Controller/Policy
    }

    public function rules(): array
    {
        $roleId = $this->route('role') ? $this->route('role')->id : null;
        
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:core_roles,slug,' . $roleId,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:core_permissions,id',
        ];
    }
}
