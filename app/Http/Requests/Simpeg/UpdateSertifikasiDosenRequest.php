<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSertifikasiDosenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'sometimes|required|exists:simpeg_pegawai,id',
            'jenis_sertifikasi_id' => 'sometimes|required|exists:simpeg_master_jenis_sertifikasi,id',
            'nama_sertifikat' => 'sometimes|required|string|max:255',
            'bidang_studi' => 'sometimes|required|string|max:150',
            'nomor_registrasi' => 'nullable|string|max:100',
            'nomor_sk' => 'nullable|string|max:100',
            'tahun_sertifikasi' => 'sometimes|required|integer|min:1970|max:' . (date('Y') + 1),
            'penyelenggara' => 'sometimes|required|string|max:200',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_sertifikasi_id.exists' => 'Jenis sertifikasi tidak ditemukan di master data.',
            'file.max' => 'Ukuran berkas sertifikat maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
