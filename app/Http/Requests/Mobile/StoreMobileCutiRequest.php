<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreMobileCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simpeg.cuti.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'master_jenis_cuti_id' => 'required|exists:simpeg_master_jenis_cuti,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jumlah_hari' => 'nullable|integer|min:1',
            'alasan' => 'required|string|min:5',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'master_jenis_cuti_id.required' => 'Jenis cuti wajib dipilih.',
            'master_jenis_cuti_id.exists' => 'Jenis cuti yang dipilih tidak valid.',
            'tanggal_mulai.required' => 'Tanggal mulai cuti wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai cuti wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'alasan.required' => 'Alasan pengajuan cuti wajib diisi.',
            'alasan.min' => 'Alasan pengajuan cuti minimal 5 karakter.',
            'file_pendukung.max' => 'Ukuran berkas pendukung maksimal 5MB.',
        ];
    }
}
