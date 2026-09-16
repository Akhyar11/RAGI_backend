<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreIzinJamKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('pegawai_id') && $this->user()?->pegawai?->id) {
            $this->merge([
                'pegawai_id' => $this->user()->pegawai->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'master_jenis_izin_id' => 'required|exists:simpeg_master_jenis_izin_jam_kerja,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'alasan' => 'required|string|min:5',
            'file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai pemohon wajib dipilih.',
            'pegawai_id.exists' => 'Data pegawai tidak valid.',
            'master_jenis_izin_id.required' => 'Jenis izin jam kerja wajib dipilih.',
            'master_jenis_izin_id.exists' => 'Jenis izin jam kerja tidak valid.',
            'tanggal.required' => 'Tanggal izin wajib diisi.',
            'jam_mulai.required' => 'Jam mulai izin wajib diisi.',
            'jam_selesai.required' => 'Jam selesai izin wajib diisi.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
            'alasan.required' => 'Alasan izin wajib diisi.',
            'alasan.min' => 'Alasan izin minimal 5 karakter.',
            'file_bukti.max' => 'Ukuran berkas lampiran maksimal 10MB.',
        ];
    }
}
