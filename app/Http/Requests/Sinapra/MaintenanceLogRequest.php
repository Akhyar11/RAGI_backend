<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aset_id' => 'nullable|exists:aset,id|required_without:ruangan_id',
            'ruangan_id' => 'nullable|exists:ruangan,id|required_without:aset_id',
            'judul' => 'required|string|max:150',
            'deskripsi_kerusakan' => 'required|string',
            'prioritas' => 'required|in:rendah,sedang,tinggi,darurat',
            'tanggal_lapor' => 'nullable|date',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'biaya' => 'nullable|numeric|min:0',
            'hasil_perbaikan' => 'nullable|string',
            'status' => 'required|in:dilaporkan,dalam_perbaikan,selesai,dibatalkan',
            'teknisi_id' => 'nullable|exists:users,id',
        ];
    }
}
