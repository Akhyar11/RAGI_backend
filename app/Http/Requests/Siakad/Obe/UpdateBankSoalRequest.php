<?php

namespace App\Http\Requests\Siakad\Obe;

use App\Models\Siakad\BankSoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'rps_id' => 'required|exists:siakad_rps,id',
            'rps_mingguan_id' => 'nullable|exists:siakad_rps_mingguan,id',
            'sub_cpmk_id' => 'nullable|exists:siakad_sub_cpmk,id',
            'kategori_id' => 'nullable|exists:siakad_bank_soal_kategori,id',
            'tipe_soal' => ['nullable', Rule::in(BankSoal::TIPE)],
            'tingkat_kesulitan' => ['nullable', Rule::in(BankSoal::TINGKAT)],
            'pertanyaan' => 'required|string',
            'gambar_path' => 'nullable|string|max:255',
            'bobot' => 'nullable|numeric|min:0|max:100',
            'kunci_jawaban' => 'nullable|string',
            'pembahasan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'rps_id.required' => 'RPS wajib dipilih.',
        ];
    }
}
