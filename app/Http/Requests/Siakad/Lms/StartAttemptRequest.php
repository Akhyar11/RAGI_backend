<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('siakad.kelas.read') || $this->user()?->hasPermission('siakad.krs.read'));
    }

    public function rules(): array
    {
        return [
            'kode_akses' => 'nullable|string|max:20',
        ];
    }
}
