<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSikeuAkunMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sikeu.akuntansi.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'akun_id_baru'   => 'required|integer|exists:sikeu_akun_keuangan,id',
            'nama_akun_lama' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'akun_id_baru.required' => 'ID akun target COA wajib diisi.',
            'akun_id_baru.exists'   => 'Akun target tidak ditemukan dalam bagan akun (COA) aktif.',
        ];
    }
}
