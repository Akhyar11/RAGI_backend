<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KategoriAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kategori = $this->route('kategori');
        $kategoriId = $kategori instanceof \App\Models\KategoriAset ? $kategori->id : $kategori;

        return [
            'induk_id' => 'nullable|exists:sinapra_kategori_aset,id',
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_kategori_aset', 'kode')->ignore($kategoriId),
            ],
            'nama' => 'required|string|max:150',
            'masa_manfaat_tahun' => 'nullable|integer|min:0',
            'tarif_penyusutan_persen' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
