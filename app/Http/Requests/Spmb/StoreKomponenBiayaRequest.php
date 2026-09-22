<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class StoreKomponenBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => 'nullable|string|max:50|unique:spmb_master_komponen_biaya,kode',
            'nama' => 'required|string|max:150',
            'kategori' => 'nullable|string|max:50',
            'tipe_potongan' => 'nullable|boolean',
            'urutan' => 'nullable|integer',
            'position_type' => 'nullable|string|max:20',
            'reference_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }
}
