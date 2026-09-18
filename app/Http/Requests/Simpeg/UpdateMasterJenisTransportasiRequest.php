<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterJenisTransportasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('jenis_transportasi');

        return [
            'nama' => 'sometimes|required|string|max:100',
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('simpeg_master_jenis_transportasi', 'kode')->ignore($id),
            ],
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
