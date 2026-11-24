<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreMobileSuratTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simpeg.surat_tugas.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'kategori_kegiatan_id' => 'required|exists:simpeg_master_kategori_kegiatan_tugas,id',
            'jenis_transportasi_id' => 'required|exists:simpeg_master_jenis_transportasi,id',
            'nama_kegiatan' => 'required|string|max:255',
            'jam_pelaksanaan' => 'nullable|string|max:100',
            'penyelenggara' => 'nullable|string|max:255',
            'tempat_berangkat' => 'required|string|max:255',
            'lokasi_tujuan' => 'required|string|max:255',
            'tanggal_berangkat' => 'required|date',
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_berangkat',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'maksud_tujuan' => 'required|string',
            'estimasi_biaya' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
            'file_surat_tugas' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'kategori_kegiatan_id.required' => 'Kategori kegiatan wajib dipilih.',
            'kategori_kegiatan_id.exists' => 'Kategori kegiatan yang dipilih tidak valid.',
            'jenis_transportasi_id.required' => 'Jenis transportasi wajib dipilih.',
            'jenis_transportasi_id.exists' => 'Jenis transportasi yang dipilih tidak valid.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'tempat_berangkat.required' => 'Tempat berangkat wajib diisi.',
            'lokasi_tujuan.required' => 'Lokasi tujuan wajib diisi.',
            'tanggal_berangkat.required' => 'Tanggal berangkat wajib diisi.',
            'tanggal_kembali.required' => 'Tanggal kembali wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali harus sama atau setelah tanggal berangkat.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'maksud_tujuan.required' => 'Maksud dan tujuan wajib diisi.',
            'file_surat_tugas.max' => 'Ukuran berkas surat tugas maksimal 10MB.',
        ];
    }
}
