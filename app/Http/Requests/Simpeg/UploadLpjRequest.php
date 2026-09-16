<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UploadLpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_lpj' => 'required|file|mimes:pdf|max:10240',
            'laporan_kegiatan' => 'nullable|string',
            'biaya_realisasi' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'file_lpj.required' => 'Berkas laporan pertanggungjawaban (LPJ) wajib diunggah.',
            'file_lpj.mimes' => 'Berkas LPJ wajib berformat PDF.',
            'file_lpj.max' => 'Ukuran berkas LPJ maksimal 10MB.',
        ];
    }
}
