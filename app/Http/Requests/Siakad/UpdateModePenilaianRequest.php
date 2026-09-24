<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModePenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode_penilaian' => ['required', 'string', new MasterReferensiExists('mode_penilaian')],
        ];
    }

    public function messages(): array
    {
        return [
            'mode_penilaian.required' => 'Mode penilaian wajib dipilih.',
        ];
    }
}
