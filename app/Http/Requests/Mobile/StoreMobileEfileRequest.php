<?php

namespace App\Http\Requests\Mobile;

use App\Models\Simpeg\DokumenPegawai;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMobileEfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simpeg.dokumen.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_dokumen' => 'required|string|max:255',
            'jenis_dokumen' => ['required', Rule::in(DokumenPegawai::JENIS_DOKUMEN)],
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_dokumen.required' => 'Nama dokumen wajib diisi.',
            'jenis_dokumen.required' => 'Jenis dokumen wajib dipilih.',
            'jenis_dokumen.in' => 'Jenis dokumen yang dipilih tidak valid.',
            'file.required' => 'Berkas dokumen wajib diunggah.',
            'file.mimes' => 'Berkas dokumen wajib berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas dokumen maksimal 5MB.',
        ];
    }
}
