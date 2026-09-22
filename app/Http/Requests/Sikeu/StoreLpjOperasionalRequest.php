<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreLpjOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_pelaksanaan' => 'required|date',
            'total_realisasi' => 'required|numeric|min:1',
            'rincian_keterangan' => 'nullable|string',
            'file_nota_kuitansi' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'metode_sisa' => 'required|in:belum_ditentukan,kembali_transfer,pakai_lagi',
            'bukti_pengembalian' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'nomor_rekening_tujuan' => 'nullable|string|max:100',
            'tambahan' => 'nullable|array',
            'tambahan.*.keterangan' => 'required_with:tambahan|string|max:255',
            'tambahan.*.qty' => 'required_with:tambahan|numeric|min:0.01',
            'tambahan.*.satuan' => 'nullable|string|max:50',
            'tambahan.*.harga_satuan' => 'required_with:tambahan|numeric|min:1',
            'tambahan.*.file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }
}
