<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterJenisPelatihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:150|unique:simpeg_master_jenis_pelatihan,nama',
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
