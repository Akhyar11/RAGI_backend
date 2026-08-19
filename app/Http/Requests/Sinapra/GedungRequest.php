<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class GedungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $gedungId = $this->route('gedung') ? $this->route('gedung')->id : null;

        return [
            'kode' => 'required|string|max:50|unique:gedung,kode,' . $gedungId,
            'nama' => 'required|string|max:150',
            'jumlah_lantai' => 'required|integer|min:1',
            'alamat' => 'nullable|string',
            'tahun_bangun' => 'nullable|integer|min:1900|max:' . date('Y'),
            'luas_m2' => 'nullable|numeric|min:0',
            'status' => 'required|in:aktif,renovasi,nonaktif',
        ];
    }
}
