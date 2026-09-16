<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRealisasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:simpeg_skp_item,id',
            'items.*.realisasi_output' => 'nullable|string|max:255',
            'items.*.realisasi_mutu' => 'nullable|numeric|min:0|max:100',
            'items.*.realisasi_waktu' => 'nullable|string|max:100',
            'items.*.realisasi_biaya' => 'nullable|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
            'items.*.berkas_bukti' => 'nullable|file|mimes:pdf,zip,doc,docx|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Daftar butir realisasi SKP wajib ada.',
            'items.*.id.required' => 'ID butir SKP wajib disertakan.',
            'items.*.id.exists' => 'Butir SKP tidak ditemukan.',
            'items.*.berkas_bukti.mimes' => 'Berkas bukti fisik luaran harus berformat PDF, ZIP, DOC, atau DOCX.',
            'items.*.berkas_bukti.max' => 'Ukuran berkas bukti maksimal 20MB.',
        ];
    }
}
