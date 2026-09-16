<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterKomponenGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode' => 'required|string|max:50|unique:simpeg_master_komponen_gaji,kode',
            'nama' => 'required|string|max:150',
            'jenis' => 'required|in:pendapatan,potongan',
            'tipe_nilai' => 'required|in:tetap,rumus_sks,rumus_kehadiran,rumus_pph21,persentase',
            'nilai_default' => 'required|numeric|min:0',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'integer|min:1',
            'keterangan' => 'nullable|string',
        ];
    }
}
