<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTandaTanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_tanda_tangan' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'judul' => 'nullable|string|max:100',
            'tipe' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file_tanda_tangan.file' => 'Berkas yang diunggah harus berupa file.',
            'file_tanda_tangan.mimes' => 'Format berkas tanda tangan harus PNG, JPG, atau JPEG.',
            'file_tanda_tangan.max' => 'Ukuran berkas tanda tangan maksimal 2 MB.',
        ];
    }
}
