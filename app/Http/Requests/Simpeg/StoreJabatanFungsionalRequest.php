<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreJabatanFungsionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->hasPermission('simpeg.create') || $this->user()->hasRole('admin'));
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|unique:simpeg_jabatan_fungsional_akademik,nama',
            'angka_kredit_min' => 'nullable|integer',
            'angka_kredit_max' => 'nullable|integer',
            'golongan' => 'required|string|exists:simpeg_master_golongan_pangkat,kode',
            'golongan_pangkat_id' => 'nullable|integer|exists:simpeg_master_golongan_pangkat,id',
            'tunjangan_nominal' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jabatan fungsional wajib diisi.',
            'nama.unique' => 'Nama jabatan fungsional sudah digunakan.',
            'golongan.required' => 'Golongan jabatan fungsional wajib diisi.',
            'golongan.exists' => 'Golongan jabatan fungsional yang dipilih tidak valid.',
        ];
    }
}
