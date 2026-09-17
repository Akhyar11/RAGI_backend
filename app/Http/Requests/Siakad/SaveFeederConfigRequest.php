<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class SaveFeederConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url'      => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ];
    }
}
