<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class PresensiPengajaranIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simpeg.presensi.pengajaran.read');
    }

    public function rules(): array
    {
        return [
            'periode' => ['required', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort_by' => ['nullable', 'in:nama_lengkap,nidn'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
