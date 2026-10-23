<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Models\Lms\LmsSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanLmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('lms.pengaturan.manage');
    }

    public function rules(): array
    {
        return [
            LmsSetting::KEY_FITUR_PRESENSI => 'sometimes|boolean',
            LmsSetting::KEY_MAX_SOAL_IMPORT => 'sometimes|integer|min:1|max:1000',
            LmsSetting::KEY_UPLOAD_MAX_SIZE_MB => 'sometimes|integer|min:1|max:200',
            LmsSetting::KEY_VIDEO_MAX_SIZE_MB => 'sometimes|integer|min:1|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            '*.integer' => 'Nilai pengaturan harus berupa angka bulat.',
            '*.boolean' => 'Nilai pengaturan harus berupa boolean.',
            '*.min' => 'Nilai pengaturan di bawah batas minimum.',
            '*.max' => 'Nilai pengaturan melebihi batas maksimum.',
        ];
    }
}
