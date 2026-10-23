<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi edit pesan forum.
 *
 * Hak mengedit bersifat tingkat resource (pemilik pesan atau pengelola kelas),
 * sehingga otorisasinya ditangani `ForumPostPolicy::update()` di controller.
 */
class UpdateForumPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('lms.forum.create')
            || (bool) $this->user()?->hasPermission('lms.forum.manage');
    }

    public function rules(): array
    {
        return [
            'isi' => 'required|string|max:5000',
        ];
    }
}
