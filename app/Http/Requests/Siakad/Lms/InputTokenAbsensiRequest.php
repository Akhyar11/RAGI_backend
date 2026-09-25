<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class InputTokenAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('siakad.kelas.read') || $this->user()?->hasPermission('siakad.krs.read'));
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|size:6',
        ];
    }
}
