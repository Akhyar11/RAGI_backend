<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePeminjamanRuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_approved' => 'required|boolean',
            'catatan_penolakan' => 'nullable|string|required_if:is_approved,false|max:500',
        ];
    }
}
