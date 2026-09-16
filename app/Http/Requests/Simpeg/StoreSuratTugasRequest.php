<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuratTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'kategori_kegiatan_id' => 'required|exists:simpeg_master_kategori_kegiatan_tugas,id',
            'jenis_transportasi_id' => 'required|exists:simpeg_master_jenis_transportasi,id',
            'nama_kegiatan' => 'required|string|max:255',
            'tempat_berangkat' => 'required|string|max:255',
            'lokasi_tujuan' => 'required|string|max:255',
            'tanggal_berangkat' => 'required|date',
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_berangkat',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'maksud_tujuan' => 'required|string',
            'beban_anggaran' => 'nullable|string|max:255',
            'estimasi_biaya' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
            'kendaraan_dinas' => 'nullable|string|max:255',
            'nama_driver' => 'nullable|string|max:255',
            'kontak_driver' => 'nullable|string|max:50',
            'anggota' => 'nullable|array',
            'anggota.*.pegawai_id' => 'required_with:anggota|exists:simpeg_pegawai,id',
            'anggota.*.peran' => 'nullable|string|max:100',
            'anggota.*.keterangan' => 'nullable|string|max:255',
            'file_surat_tugas' => 'nullable|file|mimes:pdf|max:10240',
            'file_lpj' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Penanggung jawab / ketua tim wajib dipilih.',
            'pegawai_id.exists' => 'Data pegawai penanggung jawab tidak valid.',
            'kategori_kegiatan_id.required' => 'Kategori kegiatan tugas dinas wajib dipilih.',
            'kategori_kegiatan_id.exists' => 'Kategori kegiatan tugas tidak valid.',
            'jenis_transportasi_id.required' => 'Moda transportasi wajib dipilih.',
            'jenis_transportasi_id.exists' => 'Moda transportasi tidak valid.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'tempat_berangkat.required' => 'Tempat berangkat wajib diisi.',
            'lokasi_tujuan.required' => 'Lokasi tujuan kedinasan wajib diisi.',
            'tanggal_berangkat.required' => 'Tanggal berangkat wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali harus sama atau setelah tanggal berangkat.',
            'tanggal_mulai.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai kegiatan harus sama atau setelah tanggal mulai.',
            'maksud_tujuan.required' => 'Maksud dan tujuan kedinasan wajib diisi.',
            'file_surat_tugas.mimes' => 'Berkas surat tugas resmi wajib berformat PDF.',
            'file_surat_tugas.max' => 'Ukuran berkas surat tugas maksimal 10MB.',
            'file_lpj.mimes' => 'Berkas laporan LPJ wajib berformat PDF.',
            'file_lpj.max' => 'Ukuran berkas laporan LPJ maksimal 10MB.',
        ];
    }
}
