<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasKecilTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referensi_kategori_id' => 'nullable|exists:spmb_master_referensi,id',
            'uraian' => 'required|string|min:3|max:2000',
            'penerima' => 'nullable|string|max:191',
            'nominal' => 'required|numeric|min:1',
            'tanggal_transaksi' => 'required|date|before_or_equal:today',
            'file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'keterangan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'uraian.required' => 'Uraian transaksi wajib diisi.',
            'referensi_kategori_id.exists' => 'Kategori transaksi tidak valid (harus dari master).',
            'nominal.min' => 'Nominal transaksi harus lebih dari nol.',
            'tanggal_transaksi.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
            'file_bukti.mimes' => 'Bukti transaksi harus berformat pdf/jpg/jpeg/png.',
            'file_bukti.max' => 'Ukuran bukti transaksi maksimal 5 MB.',
        ];
    }
}