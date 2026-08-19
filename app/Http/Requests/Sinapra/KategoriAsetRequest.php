<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class KategoriAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kategoriId = $this->route('kategori') ? $this->route('kategori')->id : null;

        return [
            'induk_id' => 'nullable|exists:kategori_aset,id',
            'kode' => 'required|string|max:50|unique:kategori_aset,kode,' . $kategoriId,
            'nama' => 'required|string|max:150',
            'masa_manfaat_tahun' => 'nullable|integer|min:0',
            'tarif_penyusutan_persen' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
