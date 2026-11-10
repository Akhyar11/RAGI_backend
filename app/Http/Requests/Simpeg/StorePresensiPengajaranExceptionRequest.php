<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StorePresensiPengajaranExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simpeg.presensi.pengajaran.manage');
    }

    public function rules(): array
    {
        return [
            'dosen_id' => ['required', 'integer', 'exists:siakad_dosen,id'],
            'periode' => ['required', 'date_format:Y-m'],
            'valid_until' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
