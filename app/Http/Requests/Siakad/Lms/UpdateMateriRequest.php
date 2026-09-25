<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMateriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'judul'          => 'sometimes|required|string|max:255',
            'deskripsi'      => 'nullable|string',
            'tipe_konten_id' => 'sometimes|required|integer|exists:spmb_master_referensi,id',
            'link_eksternal' => 'nullable|string|max:500',
            'urutan'         => 'nullable|integer|min:1',
            'is_published'   => 'nullable|boolean',
        ];
    }
}
