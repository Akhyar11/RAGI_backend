<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiwayatTesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jenis_tes_id' => 'required|exists:simpeg_master_jenis_tes,id',
            'nama_tes' => 'required|string|max:200',
            'penyelenggara' => 'required|string|max:200',
            'tahun' => 'required|integer|min:1970|max:' . (date('Y') + 1),
            'skor' => 'required|numeric|min:0',
            'masa_berlaku' => 'nullable|date',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai wajib ditentukan.',
            'jenis_tes_id.required' => 'Jenis tes kemampuan wajib dipilih.',
            'jenis_tes_id.exists' => 'Jenis tes tidak ditemukan di master data.',
            'nama_tes.required' => 'Nama tes wajib diisi.',
            'penyelenggara.required' => 'Lembaga penyelenggara tes wajib diisi.',
            'skor.required' => 'Skor atau hasil tes wajib diisi.',
            'tahun.required' => 'Tahun pelaksanaan tes wajib diisi.',
            'file.max' => 'Ukuran berkas sertifikat tes maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
