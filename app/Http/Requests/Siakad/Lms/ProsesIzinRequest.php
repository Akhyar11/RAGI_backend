<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class ProsesIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'status_id'     => 'required|integer|exists:spmb_master_referensi,id',
            'catatan_dosen' => 'nullable|string|max:500',
        ];
    }
}
