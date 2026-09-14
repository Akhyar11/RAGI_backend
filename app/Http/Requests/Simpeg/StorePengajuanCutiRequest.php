<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StorePengajuanCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'master_jenis_cuti_id' => 'required|exists:simpeg_master_jenis_cuti,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'jumlah_hari' => 'nullable|integer|min:1',
            'alasan' => 'required|string',
            'file_pendukung' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai pemohon wajib dipilih.',
            'pegawai_id.exists' => 'Data pegawai pemohon tidak valid.',
            'master_jenis_cuti_id.required' => 'Jenis izin/cuti wajib dipilih.',
            'master_jenis_cuti_id.exists' => 'Jenis izin/cuti yang dipilih tidak ditemukan.',
            'tanggal_mulai.required' => 'Tanggal mulai cuti wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'alasan.required' => 'Alasan pengajuan cuti wajib diisi.',
            'file.max' => 'Ukuran berkas lampiran maksimal 10MB.',
        ];
    }
}
