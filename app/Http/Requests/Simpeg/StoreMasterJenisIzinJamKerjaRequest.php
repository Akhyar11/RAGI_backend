<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterJenisIzinJamKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.cuti.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:150',
            'kode' => 'required|string|max:50|unique:simpeg_master_jenis_izin_jam_kerja,kode',
            'tipe_potongan' => 'required|string|max:50',
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis izin jam kerja wajib diisi.',
            'kode.required' => 'Kode jenis izin jam kerja wajib diisi.',
            'kode.unique' => 'Kode jenis izin jam kerja sudah digunakan.',
            'tipe_potongan.required' => 'Tipe potongan jam kerja wajib diisi.',
        ];
    }
}
