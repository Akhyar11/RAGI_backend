<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_keberadaan' => ['required', 'in:sesuai,tidak_ditemukan,rusak,berlebih'],
            'kondisi_fisik' => ['nullable', 'in:baik,rusak_ringan,rusak_berat'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
