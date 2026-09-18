<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterKategoriSkpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'kode' => 'required|string|max:50|unique:simpeg_master_kategori_skp,kode',
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kategori sasaran kinerja wajib diisi.',
            'kode.required' => 'Kode kategori SKP wajib diisi.',
            'kode.unique' => 'Kode kategori SKP ini sudah digunakan.',
        ];
    }
}
