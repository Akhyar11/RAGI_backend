<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankSoalOpsiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'teks' => 'required|string',
            'gambar_path' => 'nullable|string|max:255',
            'is_benar' => 'nullable|boolean',
            'urutan' => 'nullable|integer|min:0',
        ];
    }
}
