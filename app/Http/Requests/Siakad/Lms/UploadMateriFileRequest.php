<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Http\Requests\Siakad\Lms\Concerns\ResolvesMateriUploadLimit;
use Illuminate\Foundation\Http\FormRequest;

class UploadMateriFileRequest extends FormRequest
{
    use ResolvesMateriUploadLimit;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:'.$this->materiMaxSizeKb(),
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => $this->materiMaxSizeMessage(),
        ];
    }
}
