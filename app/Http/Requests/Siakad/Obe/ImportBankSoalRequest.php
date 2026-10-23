<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'rps_id' => 'required|exists:siakad_rps,id',
            'rps_mingguan_id' => 'nullable|exists:siakad_rps_mingguan,id',
            'sub_cpmk_id' => 'nullable|exists:siakad_sub_cpmk,id',
            'kategori_id' => 'nullable|exists:siakad_bank_soal_kategori,id',
            'tingkat_kesulitan' => 'nullable|in:mudah,sedang,sukar',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File import soal wajib diunggah.',
            'file.mimes' => 'Format file harus XLSX, XLS, atau CSV.',
            'file.max' => 'Ukuran file import maksimal 10 MB.',
            'rps_id.required' => 'RPS tujuan wajib dipilih.',
            'rps_id.exists' => 'RPS tujuan tidak valid.',
        ];
    }
}
