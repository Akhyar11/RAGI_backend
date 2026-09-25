<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class KumpulkanTugasRequest extends FormRequest
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
}
