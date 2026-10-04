<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KlasifikasiSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('klasifikasi') ?? $this->route('id');

        return [
            'kode' => [
                $this->isMethod('POST') ? 'required' : 'sometimes|required',
                'string',
                'max:50',
                Rule::unique('core_arsip_klasifikasi', 'kode')->ignore($id)->whereNull('deleted_at'),
            ],
            'nama' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'keterangan' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode singkatan klasifikasi / unit wajib diisi.',
            'kode.unique' => 'Kode ini sudah terdaftar dalam sistem.',
            'nama.required' => 'Nama lengkap klasifikasi / unit wajib diisi.',
            'kategori.required' => 'Kategori (unit / perihal / klasifikasi) wajib dipilih.',
        ];
    }
}
