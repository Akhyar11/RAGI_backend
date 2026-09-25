<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBebasTanggunganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_approved' => 'required|boolean',
            'catatan' => 'nullable|string|max:500',
        ];
    }
}
