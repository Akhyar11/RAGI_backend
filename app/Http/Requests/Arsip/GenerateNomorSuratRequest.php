<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class GenerateNomorSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => 'nullable|string|in:satuan,bulk',
            'tanggal_surat' => 'required|date',
            'kode_unit' => 'required|string|max:50',
            'kode_klasifikasi' => 'required|string|max:50',
            'perihal' => 'required|string|max:255',
            'tujuan' => 'nullable|string|max:200',
            'status' => 'nullable|string|in:terpakai,direservasi',
            'module_origin' => 'nullable|string|max:50',
            'catatan' => 'nullable|string',
            'jumlah_nomor' => 'nullable|integer|min:1|max:500',
            'keterangan_item' => 'nullable|array',
            'keterangan_item.*' => 'nullable|string|max:255',
            'tujuan_item' => 'nullable|array',
            'tujuan_item.*' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_surat.required' => 'Tanggal surat wajib diisi.',
            'kode_unit.required' => 'Kode unit / jenjang wajib diisi.',
            'kode_klasifikasi.required' => 'Kode klasifikasi surat wajib diisi.',
            'perihal.required' => 'Perihal surat wajib diisi.',
            'jumlah_nomor.min' => 'Jumlah nomor surat minimal 1.',
            'jumlah_nomor.max' => 'Jumlah nomor surat maksimal 500 sekaligus.',
        ];
    }
}
