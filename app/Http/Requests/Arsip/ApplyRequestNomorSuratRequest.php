<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequestNomorSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_origin' => 'required|string|max:50',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|integer',
            'perihal' => 'required|string|max:255',
            'tujuan' => 'nullable|string|max:200',
            'tanggal_surat' => 'required|date',
            'kode_unit' => 'nullable|string|max:50',
            'kode_klasifikasi' => 'nullable|string|max:50',
            'jumlah_nomor' => 'nullable|integer|min:1|max:500',
            'catatan_pemohon' => 'nullable|string',
            'lampiran' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp,doc,docx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'module_origin.required' => 'Asal modul pemohon wajib diisi.',
            'perihal.required' => 'Perihal surat wajib diisi.',
            'tanggal_surat.required' => 'Tanggal surat yang diajukan wajib diisi.',
            'kode_unit.required' => 'Kode unit / jenjang wajib diisi.',
            'kode_klasifikasi.required' => 'Kode klasifikasi surat wajib diisi.',
            'lampiran.max' => 'Ukuran berkas lampiran maksimal 10 MB.',
        ];
    }
}
