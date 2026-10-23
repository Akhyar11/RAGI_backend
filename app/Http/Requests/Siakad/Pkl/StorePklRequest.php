<?php

namespace App\Http\Requests\Siakad\Pkl;

use Illuminate\Foundation\Http\FormRequest;

class StorePklRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.pkl.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'jenis' => 'required|in:pkl_reguler,magang_industri,magang_mbkm,studi_independen',
            'judul_laporan' => 'nullable|string|max:255',
            'instansi_nama' => 'required|string|min:3|max:200',
            'instansi_alamat' => 'nullable|string',
            'instansi_kota' => 'nullable|string|max:100',
            'pembimbing_lapangan_nama' => 'nullable|string|max:150',
            'pembimbing_lapangan_kontak' => 'nullable|string|max:50',
            'pembimbing_lapangan_jabatan' => 'nullable|string|max:100',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'berkas_permohonan' => 'nullable|file|mimes:pdf|max:5120',
        ];
    }
}
