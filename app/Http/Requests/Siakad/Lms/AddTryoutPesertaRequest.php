<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class AddTryoutPesertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
        ];
    }
}
