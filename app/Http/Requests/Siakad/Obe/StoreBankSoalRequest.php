<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'id' => 'nullable|exists:siakad_bank_soal,id',
            'rps_id' => 'required|exists:siakad_rps,id',
            'rps_mingguan_id' => 'nullable|exists:siakad_rps_mingguan,id',
            'sub_cpmk_id' => 'nullable|exists:siakad_sub_cpmk,id',
            'kategori_id' => 'nullable|exists:siakad_bank_soal_kategori,id',
            'tipe_soal' => 'nullable|in:pilihan_ganda,isian_singkat,uraian',
            'tingkat_kesulitan' => 'nullable|in:mudah,sedang,sukar',
            'pertanyaan' => 'required|string',
            'gambar_path' => 'nullable|string|max:255',
            'bobot' => 'nullable|numeric|min:0|max:100',
            'kunci_jawaban' => 'nullable|string',
            'pembahasan' => 'nullable|string',
        ];
    }
}
