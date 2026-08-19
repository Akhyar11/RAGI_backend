<?php

namespace App\Http\Requests\Sippm;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandarIku5ProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => 'required|integer|exists:unit_kerja,id',
            'tahun_akademik' => 'required|string|max:10',
            'target_publikasi_scopus' => 'required|integer|min:0',
            'target_publikasi_sinta' => 'required|integer|min:0',
            'target_hki_paten' => 'required|integer|min:0',
            'target_buku_isbn' => 'required|integer|min:0',
        ];
    }
}
