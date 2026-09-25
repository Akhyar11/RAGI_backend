<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('vendor');

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_master_vendor', 'kode')->ignore($id)->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_rekanan' => ['required', 'string', 'max:60'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'pic_nama' => ['nullable', 'string', 'max:100'],
            'pic_kontak' => ['nullable', 'string', 'max:50'],
            'nomor_npwp' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode vendor / rekanan wajib diisi.',
            'kode.unique' => 'Kode vendor / rekanan sudah digunakan.',
            'nama.required' => 'Nama vendor / rekanan wajib diisi.',
            'jenis_rekanan.required' => 'Jenis rekanan wajib dipilih.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
