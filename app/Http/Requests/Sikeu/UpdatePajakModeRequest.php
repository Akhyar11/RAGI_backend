<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePajakModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sikeu.pajak_akun.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'mode' => 'required|in:dev,publik',
        ];
    }
}
