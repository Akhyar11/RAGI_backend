<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterJenisTransportasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'kode' => 'required|string|max:50|unique:simpeg_master_jenis_transportasi,kode',
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama moda transportasi wajib diisi.',
            'kode.required' => 'Kode unik transportasi wajib diisi.',
            'kode.unique' => 'Kode transportasi ini sudah digunakan.',
        ];
    }
}
