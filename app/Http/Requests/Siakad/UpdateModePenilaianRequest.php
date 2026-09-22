<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModePenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('siakad.master.manage');
    }

    public function rules(): array
    {
        return [
            'mode_penilaian' => 'required|in:full_obe,semi_obe,konvensional',
        ];
    }
}
