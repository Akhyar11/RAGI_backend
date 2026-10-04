<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class PlottingProdiRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => 'nullable|integer|exists:core_roles,id',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'required|integer|exists:core_roles,id',
            'keterangan' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.integer' => 'ID role harus berupa bilangan bulat.',
            'role_id.exists' => 'Role yang dipilih tidak valid di sistem.',
            'role_ids.array' => 'Data role_ids harus berupa array ID role.',
            'role_ids.*.exists' => 'Salah satu role yang dipilih tidak valid di sistem.',
        ];
    }
}
