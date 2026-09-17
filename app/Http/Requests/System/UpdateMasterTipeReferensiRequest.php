<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterTipeReferensiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->route('id') ?: $this->route('master_tipe_referensi');

        return [
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('core_tipe_referensi', 'kode')->ignore($id),
            ],
            'nama' => 'required|string|max:100',
            'modul' => 'required|string|in:global,spmb,siakad,simpeg,sikeu',
            'deskripsi' => 'nullable|string|max:500',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode tipe referensi wajib diisi.',
            'kode.regex' => 'Kode hanya boleh menggunakan huruf kecil, angka, dan garis bawah (_).',
            'kode.unique' => 'Kode tipe referensi ini sudah digunakan.',
            'nama.required' => 'Nama tipe referensi wajib diisi.',
            'modul.required' => 'Modul wajib dipilih.',
            'modul.in' => 'Pilihan modul tidak valid (global, spmb, siakad, simpeg, sikeu).',
        ];
    }
}
