<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterJenisPelatihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        $id = $this->route('jenis_pelatihan') ?? $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:150|unique:simpeg_master_jenis_pelatihan,nama,' . $id,
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis pelatihan wajib diisi.',
            'nama.unique' => 'Nama jenis pelatihan sudah terdaftar.',
        ];
    }
}
