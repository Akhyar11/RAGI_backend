<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMahasiswaBeasiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beasiswa_id' => 'nullable|integer|exists:sikeu_beasiswa,id',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'status' => 'nullable|in:aktif,nonaktif,selesai',
        ];
    }

    public function messages(): array
    {
        return [
            'beasiswa_id.exists' => 'Program beasiswa tidak valid atau tidak ditemukan.',
            'berlaku_mulai.date' => 'Format tanggal mulai berlaku tidak valid.',
            'berlaku_sampai.date' => 'Format tanggal berakhir berlaku tidak valid.',
            'berlaku_sampai.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
            'status.in' => 'Status beasiswa harus berupa aktif, nonaktif, atau selesai.',
        ];
    }
}
