<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaketMkRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.kelas.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'semester_anjuran' => 'nullable|integer|min:1|max:14',
            'is_wajib' => 'nullable|boolean',
        ];
    }
}
