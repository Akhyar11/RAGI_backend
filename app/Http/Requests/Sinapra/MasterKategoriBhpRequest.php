<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterKategoriBhpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('kategori_bhp');

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_master_kategori_bhp', 'kode')->ignore($id),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode kategori BHP wajib diisi.',
            'kode.unique' => 'Kode kategori BHP sudah digunakan.',
            'nama.required' => 'Nama kategori BHP wajib diisi.',
        ];
    }
}
