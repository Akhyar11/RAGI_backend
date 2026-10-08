<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('admin_siakad') || $user->hasPermission('siakad.master.manage')));
    }

    public function rules(): array
    {
        return [
            'jabatan' => 'sometimes|nullable|string|max:100',
            'can_approve_rps' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
