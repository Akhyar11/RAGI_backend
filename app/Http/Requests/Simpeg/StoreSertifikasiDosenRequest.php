<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreSertifikasiDosenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jenis_sertifikasi_id' => 'required|exists:simpeg_master_jenis_sertifikasi,id',
            'nama_sertifikat' => 'required|string|max:255',
            'bidang_studi' => 'required|string|max:150',
            'nomor_registrasi' => 'nullable|string|max:100',
            'nomor_sk' => 'nullable|string|max:100',
            'tahun_sertifikasi' => 'required|integer|min:1970|max:' . (date('Y') + 1),
            'penyelenggara' => 'required|string|max:200',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai wajib ditentukan.',
            'pegawai_id.exists' => 'Data pegawai tidak valid.',
            'jenis_sertifikasi_id.required' => 'Jenis sertifikasi wajib dipilih.',
            'jenis_sertifikasi_id.exists' => 'Jenis sertifikasi tidak ditemukan di master data.',
            'nama_sertifikat.required' => 'Nama sertifikat wajib diisi.',
            'bidang_studi.required' => 'Bidang studi / keahlian wajib diisi.',
            'tahun_sertifikasi.required' => 'Tahun sertifikasi wajib diisi.',
            'penyelenggara.required' => 'Lembaga / perguruan tinggi penyelenggara wajib diisi.',
            'file.max' => 'Ukuran berkas sertifikat maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
