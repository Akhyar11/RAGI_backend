<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class CreateMutasiAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['required', 'integer', 'exists:sinapra_aset,id'],
            'ruangan_tujuan_id' => ['required', 'integer', 'exists:sinapra_ruangan,id'],
            'alasan' => ['required', 'string', 'min:5'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
