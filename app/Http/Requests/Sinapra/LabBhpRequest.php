<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabBhpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bhpId = $this->route('lab_bhp') ?? $this->route('id');

        return [
            'ruangan_id' => $this->isMethod('POST') ? 'required|exists:sinapra_ruangan,id' : 'sometimes|exists:sinapra_ruangan,id',
            'kode_bhp' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:50',
                Rule::unique('sinapra_lab_bhp', 'kode_bhp')->ignore($bhpId),
            ],
            'nama_bhp' => $this->isMethod('POST') ? 'required|string|max:150' : 'sometimes|string|max:150',
            'kategori_bhp_id' => 'nullable|exists:sinapra_master_kategori_bhp,id',
            'satuan_id' => 'nullable|exists:sinapra_master_satuan,id',
            'kategori' => 'nullable|string|max:50',
            'stok_saat_ini' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|numeric|min:0',
            'satuan' => 'nullable|string|max:30',
            'spesifikasi' => 'nullable|string',
            'lokasi_penyimpanan' => 'nullable|string|max:100',
        ];
    }
}
