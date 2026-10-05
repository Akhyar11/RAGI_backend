<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('lms.forum.create');
    }

    public function rules(): array
    {
        return [
            'isi' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:lms_forum_post,id',
        ];
    }
}
