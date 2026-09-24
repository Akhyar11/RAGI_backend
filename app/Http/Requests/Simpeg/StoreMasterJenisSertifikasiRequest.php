<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterJenisSertifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:150',
            'kode' => 'required|string|max:50|unique:simpeg_master_jenis_sertifikasi,kode',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis sertifikasi wajib diisi.',
            'kode.required' => 'Kode jenis sertifikasi wajib diisi.',
            'kode.unique' => 'Kode jenis sertifikasi sudah digunakan.',
        ];
    }
}
