<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterKategoriSkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.sk_pegawai.manage') || $user->hasPermission('simpeg.sk.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        $id = $this->route('kategori_sk') ?? $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:150',
            'kode' => 'sometimes|required|string|max:50|unique:simpeg_master_kategori_sk,kode,' . $id,
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kategori SK wajib diisi.',
            'kode.required' => 'Kode kategori SK wajib diisi.',
            'kode.unique' => 'Kode kategori SK sudah digunakan.',
        ];
    }
}
