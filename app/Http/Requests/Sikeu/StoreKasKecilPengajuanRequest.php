<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasKecilPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul_pengajuan' => 'required|string|min:3|max:255',
            'keperluan' => 'nullable|string',
            'nominal_diajukan' => 'required|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'judul_pengajuan.required' => 'Judul pengajuan wajib diisi.',
            'nominal_diajukan.min' => 'Nominal pengajuan harus lebih dari nol.',
        ];
    }
}