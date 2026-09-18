<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterKategoriSkpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('kategori_skp');

        return [
            'nama' => 'sometimes|required|string|max:100',
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('simpeg_master_kategori_skp', 'kode')->ignore($id),
            ],
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
