<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiwayatPelatihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'sometimes|required|exists:simpeg_pegawai,id',
            'nama_kegiatan' => 'sometimes|required|string|max:255',
            'jenis_pelatihan_id' => 'nullable|exists:simpeg_master_jenis_pelatihan,id',
            'peran_id' => 'sometimes|required|exists:simpeg_master_peran_pelatihan,id',
            'tingkat_id' => 'nullable|exists:simpeg_master_tingkat_kegiatan,id',
            'tanggal_mulai' => 'sometimes|required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'jumlah_jam' => 'nullable|integer|min:1',
            'penyelenggara' => 'sometimes|required|string|max:200',
            'tempat' => 'nullable|string|max:200',
            'nomor_sertifikat' => 'nullable|string|max:100',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'peran_id.exists' => 'Peran tidak ditemukan di master data.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'file.max' => 'Ukuran berkas sertifikat maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
