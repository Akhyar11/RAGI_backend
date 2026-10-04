<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreTandaTanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:core_users,id',
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'file_tanda_tangan' => 'required|file|mimes:png,jpg,jpeg|max:2048',
            'judul' => 'nullable|string|max:100',
            'tipe' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pengguna (user) wajib dipilih.',
            'user_id.exists' => 'Pengguna yang dipilih tidak terdaftar di sistem.',
            'pegawai_id.exists' => 'Data pegawai yang dipilih tidak valid.',
            'file_tanda_tangan.required' => 'Berkas gambar tanda tangan wajib diunggah.',
            'file_tanda_tangan.file' => 'Berkas yang diunggah harus berupa file.',
            'file_tanda_tangan.mimes' => 'Format berkas tanda tangan harus PNG, JPG, atau JPEG (PNG transparan disarankan).',
            'file_tanda_tangan.max' => 'Ukuran berkas tanda tangan maksimal 2 MB.',
        ];
    }
}
