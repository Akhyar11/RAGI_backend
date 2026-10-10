<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresensiLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => 'nullable|date_format:Y-m-d',
            'jam_masuk' => 'nullable|string|max:8',
            'jam_keluar' => 'nullable|string|max:8',
            'reset_clock_in' => 'nullable|boolean',
            'reset_clock_out' => 'nullable|boolean',
            'status' => 'nullable|in:hadir,terlambat,menunggu_approval,ditolak,izin,sakit,dinas,alfa',
            'status_kehadiran' => 'nullable|in:hadir,terlambat,menunggu_approval,ditolak,izin,sakit,dinas,alfa',
            'catatan' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'status.in' => 'Status harus salah satu dari: hadir, terlambat, menunggu_approval, ditolak, izin, sakit, dinas, alfa.',
            'status_kehadiran.in' => 'Status kehadiran harus salah satu dari: hadir, terlambat, menunggu_approval, ditolak, izin, sakit, dinas, alfa.',
        ];
    }
}
