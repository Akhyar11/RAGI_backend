<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi ubah (rename / pin) topik forum.
 *
 * Otorisasi tingkat resource ditangani `ForumTopikPolicy::update()`
 * di controller; Form Request hanya memastikan permission modul terpenuhi.
 */
class UpdateForumTopikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('lms.forum.manage');
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:255',
            'is_pinned' => 'nullable|boolean',
        ];
    }
}
