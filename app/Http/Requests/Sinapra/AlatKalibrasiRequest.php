<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class AlatKalibrasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aset_id' => $this->isMethod('POST') ? 'required|exists:sinapra_aset,id' : 'sometimes|exists:sinapra_aset,id',
            'vendor_id' => 'nullable|exists:sinapra_master_vendor,id',
            'institusi_kalibrasi' => 'nullable|string|max:150',
            'nomor_sertifikat' => 'nullable|string|max:100',
            'tanggal_kalibrasi' => $this->isMethod('POST') ? 'required|date' : 'sometimes|date',
            'tanggal_kadaluarsa' => $this->isMethod('POST') ? 'required|date|after_or_equal:tanggal_kalibrasi' : 'sometimes|date',
            'status_kelayakan' => 'nullable|in:laik,tidak_laik,butuh_perbaikan',
            'catatan' => 'nullable|string',
        ];
    }
}
