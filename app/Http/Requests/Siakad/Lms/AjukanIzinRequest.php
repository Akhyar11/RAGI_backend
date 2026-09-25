<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class AjukanIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('siakad.kelas.read') || $this->user()?->hasPermission('siakad.krs.read'));
    }

    public function rules(): array
    {
        return [
            'tipe_izin_id' => 'required|integer|exists:spmb_master_referensi,id',
            'alasan'       => 'required|string|max:1000',
            'file_surat'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // max 10MB
        ];
    }
}
