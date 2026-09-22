<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterTingkatKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        $id = $this->route('tingkat_kegiatan') ?? $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:150|unique:simpeg_master_tingkat_kegiatan,nama,' . $id,
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama tingkat kegiatan wajib diisi.',
            'nama.unique' => 'Nama tingkat kegiatan sudah terdaftar.',
        ];
    }
}
