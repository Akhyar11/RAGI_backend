<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalTagihanRequest extends FormRequest
{
    /**
     * Dipanggil oleh sistem eksternal tepercaya (SPMB, SIAKAD, SIMPEG, SIPPM)
     * dan pemanggil internal; otorisasi mengikuti route pemanggil.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mahasiswa_id' => 'nullable|integer|required_without:calon_mahasiswa_id',
            'calon_mahasiswa_id' => 'nullable|integer|required_without:mahasiswa_id',
            'tipe_referensi' => 'nullable|string|max:30',
            'tahun_akademik_id' => 'nullable|integer',
            'source_system' => 'required|string|max:50',
            'master_biaya_tipe' => 'nullable|string|max:30',
            'requires_approval' => 'nullable|boolean',
            'jatuh_tempo' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.master_biaya_kode' => 'required|string',
            'details.*.nominal' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string',
            'potongan' => 'nullable|array',
            'potongan.*.tipe' => 'nullable|string',
            'potongan.*.nominal_potongan' => 'required_with:potongan|numeric|min:0',
            'potongan.*.keterangan' => 'nullable|string',
        ];
    }
}
