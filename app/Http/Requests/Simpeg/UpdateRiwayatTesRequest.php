<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiwayatTesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'sometimes|required|exists:simpeg_pegawai,id',
            'jenis_tes_id' => 'sometimes|required|exists:simpeg_master_jenis_tes,id',
            'nama_tes' => 'sometimes|required|string|max:200',
            'penyelenggara' => 'sometimes|required|string|max:200',
            'tahun' => 'sometimes|required|integer|min:1970|max:' . (date('Y') + 1),
            'skor' => 'sometimes|required|numeric|min:0',
            'masa_berlaku' => 'nullable|date',
            'tautan' => 'nullable|url|max:255',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_tes_id.exists' => 'Jenis tes tidak ditemukan di master data.',
            'file.max' => 'Ukuran berkas sertifikat tes maksimal 10MB.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
