<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKopSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'sometimes|required|string|max:150',
            'versi' => 'sometimes|required|string|in:lama,baru',
            'tahun_mulai' => 'sometimes|required|integer|min:1900|max:2100',
            'tahun_selesai' => 'nullable|integer|min:1900|max:2100',
            'file_kop' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'nama_institusi' => 'nullable|string|max:200',
            'alamat_institusi' => 'nullable|string|max:255',
            'kontak_institusi' => 'nullable|string|max:150',
            'website_institusi' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kop surat wajib diisi.',
            'file_kop.mimes' => 'Berkas kop surat harus berupa file gambar (PNG, JPG, JPEG, atau WEBP).',
            'file_kop.max' => 'Ukuran berkas gambar kop surat maksimal 5 MB.',
        ];
    }
}
