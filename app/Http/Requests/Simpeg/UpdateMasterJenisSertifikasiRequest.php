<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterJenisSertifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        $id = $this->route('jenis_sertifikasi') ?? $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:150',
            'kode' => 'sometimes|required|string|max:50|unique:simpeg_master_jenis_sertifikasi,kode,' . $id,
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
