<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'window_menit' => 'nullable|integer|min:5|max:180',
        ];
    }
}
