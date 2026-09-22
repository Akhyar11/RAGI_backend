<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class StoreTahunAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('siakad.master.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => 'required|string|unique:siakad_tahun_akademik,kode',
            'nama' => 'required|string|max:255',
            'tahun_mulai' => 'nullable|integer',
            'tahun_selesai' => 'nullable|integer',
            'is_active' => 'boolean',
            'mode_penilaian' => 'nullable|string|max:20',
        ];
    }
}
