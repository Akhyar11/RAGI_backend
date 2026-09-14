<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class SetKeteranganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'tanggal' => 'required|date_format:Y-m-d',
            'status_kehadiran' => 'required|in:izin,sakit,dinas,alfa',
            'catatan' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai wajib dipilih.',
            'pegawai_id.exists' => 'Data pegawai tidak ditemukan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'status_kehadiran.required' => 'Keterangan ketidakhadiran wajib dipilih.',
            'status_kehadiran.in' => 'Keterangan harus salah satu dari: izin, sakit, dinas, alfa.',
        ];
    }
}
