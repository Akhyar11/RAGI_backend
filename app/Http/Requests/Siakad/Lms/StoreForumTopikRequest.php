<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForumTopikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('lms.forum.manage');
    }

    public function rules(): array
    {
        $kelasId = (int) $this->route('kelasId');

        return [
            'judul' => 'required|string|max:255',
            // Pertemuan harus milik kelas yang sedang dibuat topiknya, supaya
            // topik tidak bisa ditautkan ke pertemuan kelas lain.
            'pertemuan_id' => [
                'nullable',
                Rule::exists('siakad_pertemuan', 'id')->where(fn ($q) => $q->where('kelas_id', $kelasId)),
            ],
            'is_pinned' => 'nullable|boolean',
        ];
    }
}
