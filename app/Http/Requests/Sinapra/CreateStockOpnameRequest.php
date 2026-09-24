<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class CreateStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruangan_id' => ['required', 'integer', 'exists:sinapra_ruangan,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
