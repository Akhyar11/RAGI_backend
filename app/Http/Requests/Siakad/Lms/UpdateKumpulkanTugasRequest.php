<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembaruan pengumpulan tugas oleh mahasiswa.
 *
 * Berbeda dengan `KumpulkanTugasRequest`, berkas di sini opsional: mahasiswa
 * boleh memperbarui catatan saja tanpa mengunggah ulang berkas.
 */
class UpdateKumpulkanTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('siakad.kelas.read') || $this->user()?->hasPermission('siakad.krs.read'));
    }

    public function rules(): array
    {
        return [
            'catatan_mahasiswa' => 'nullable|string',
            'file'              => 'nullable|file|max:51200', // max 50MB
        ];
    }

    public function messages(): array
    {
        return [
            'catatan_mahasiswa.string' => 'Catatan harus berupa teks.',
            'file.file'                => 'Berkas yang diunggah tidak valid.',
            'file.max'                 => 'Ukuran berkas maksimal 50MB.',
        ];
    }
}
