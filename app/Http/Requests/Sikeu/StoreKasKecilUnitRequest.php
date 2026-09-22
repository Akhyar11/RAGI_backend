<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasKecilUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_kas' => 'required|string|max:191',
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'penanggung_jawab_id' => 'required|exists:core_users,id',
            'akun_keuangan_id' => 'required|exists:sikeu_akun_keuangan,id',
            'saldo_awal' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kas.required' => 'Nama kas kecil wajib diisi.',
            'fakultas_id.exists' => 'Fakultas tidak valid (harus dari master).',
            'penanggung_jawab_id.exists' => 'Penanggung jawab tidak valid.',
            'akun_keuangan_id.required' => 'Akun kas (COA kelompok aset) wajib dipilih agar jurnal kas kecil akurat.',
            'saldo_awal.min' => 'Saldo awal tidak boleh negatif.',
            'akun_keuangan_id.exists' => 'Akun keuangan tidak valid.',
        ];
    }
}