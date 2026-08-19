<?php

namespace App\Http\Requests\Sippm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStandarIku5ProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => 'sometimes|required|integer|exists:unit_kerja,id',
            'tahun_akademik' => 'sometimes|required|string|max:10',
            'target_publikasi_scopus' => 'sometimes|required|integer|min:0',
            'target_publikasi_sinta' => 'sometimes|required|integer|min:0',
            'target_hki_paten' => 'sometimes|required|integer|min:0',
            'target_buku_isbn' => 'sometimes|required|integer|min:0',
        ];
    }
}
