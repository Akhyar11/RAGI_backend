<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class AssignLaboranProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:core_users,id',
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
