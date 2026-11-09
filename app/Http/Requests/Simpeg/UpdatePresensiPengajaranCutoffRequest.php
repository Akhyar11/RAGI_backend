<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresensiPengajaranCutoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simpeg.presensi.pengajaran.manage');
    }

    public function rules(): array
    {
        return [
            'periode' => ['required', 'date_format:Y-m'],
            'cutoff_at' => ['required', 'date'],
        ];
    }
}
