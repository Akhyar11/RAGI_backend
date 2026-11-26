<?php

namespace App\Http\Requests\Mobile;

use App\Models\Simpeg\DokumenPegawai;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileEfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simpeg.dokumen.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_dokumen' => 'sometimes|required|string|max:255',
            'jenis_dokumen' => ['sometimes', 'required', Rule::in(DokumenPegawai::JENIS_DOKUMEN)],
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_dokumen.required' => 'Nama dokumen wajib diisi.',
            'jenis_dokumen.required' => 'Jenis dokumen wajib dipilih.',
            'jenis_dokumen.in' => 'Jenis dokumen yang dipilih tidak valid.',
            'file.mimes' => 'Berkas dokumen wajib berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas dokumen maksimal 5MB.',
        ];
    }
}
