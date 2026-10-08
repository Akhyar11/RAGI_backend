<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class AssignAdminProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('admin_siakad') || $user->hasPermission('siakad.master.manage')));
    }

    public function rules(): array
    {
        return [
            'program_studi_id' => 'required|integer|exists:siakad_program_studi,id',
            'user_id' => 'required|integer|exists:core_users,id',
            'jabatan' => 'nullable|string|max:100',
            'can_approve_rps' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'program_studi_id.required' => 'Program Studi wajib dipilih.',
            'program_studi_id.exists' => 'Program Studi tidak valid.',
            'user_id.required' => 'Pengguna (User) wajib dipilih.',
            'user_id.exists' => 'Pengguna tidak ditemukan.',
        ];
    }
}
