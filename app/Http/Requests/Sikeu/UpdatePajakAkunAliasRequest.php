<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePajakAkunAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sikeu.pajak_akun.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_akun_pajak' => 'present|nullable|string|max:255',
        ];
    }
}
