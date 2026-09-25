<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class StoreTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'komponen_penilaian_id' => 'nullable|integer|exists:siakad_komponen_penilaian,id',
            'judul'                 => 'required|string|max:255',
            'deskripsi'             => 'nullable|string',
            'deadline_at'           => 'nullable|date',
            'maks_nilai'            => 'nullable|integer|min:1|max:1000',
            'can_submit_late'       => 'nullable|boolean',
            'is_published'          => 'nullable|boolean',
        ];
    }
}
