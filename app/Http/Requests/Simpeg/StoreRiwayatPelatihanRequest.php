<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiwayatPelatihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'nama_kegiatan' => 'required|string|max:255',
            'jenis_pelatihan_id' => 'nullable|exists:simpeg_master_jenis_pelatihan,id',
            'peran_id' => 'required|exists:simpeg_master_peran_pelatihan,id',
            'tingkat_id' => 'nullable|exists:simpeg_master_tingkat_kegiatan,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'jumlah_jam' => 'nullable|integer|min:1',
            'penyelenggara' => 'required|string|max:200',
            'tempat' => 'nullable|string|max:200',
            'nomor_sertifikat' => 'nullable|string|max:100',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai wajib ditentukan.',
            'nama_kegiatan.required' => 'Nama kegiatan pelatihan / diklat wajib diisi.',
            'peran_id.required' => 'Peran dalam kegiatan wajib dipilih.',
            'peran_id.exists' => 'Peran tidak ditemukan di master data.',
            'tanggal_mulai.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'penyelenggara.required' => 'Lembaga penyelenggara wajib diisi.',
            'file.max' => 'Ukuran berkas sertifikat maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
