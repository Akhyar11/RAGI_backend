<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSuratTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:disetujui,ditolak',
            'nomor_surat' => 'required_if:status,disetujui|nullable|string|max:100',
            'nominal_disetujui' => 'nullable|numeric|min:0',
            'catatan_approval' => 'nullable|string',
            'file_surat_tugas' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status persetujuan wajib ditentukan.',
            'nomor_surat.required_if' => 'Nomor surat tugas resmi wajib diisi jika disetujui.',
            'nominal_disetujui.numeric' => 'Nominal disetujui harus berupa angka valid.',
            'nominal_disetujui.min' => 'Nominal disetujui tidak boleh bernilai negatif.',
            'file_surat_tugas.mimes' => 'Berkas surat tugas bertandatangan harus berformat PDF.',
            'file_surat_tugas.max' => 'Ukuran berkas surat tugas maksimal 10MB.',
        ];
    }
}
