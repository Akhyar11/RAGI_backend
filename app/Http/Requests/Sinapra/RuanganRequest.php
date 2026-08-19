<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class RuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruanganId = $this->route('ruangan') ? $this->route('ruangan')->id : null;

        return [
            'gedung_id' => 'required|exists:gedung,id',
            'kode' => 'required|string|max:50|unique:ruangan,kode,' . $ruanganId,
            'nama' => 'required|string|max:150',
            'lantai' => 'required|integer|min:1',
            'tipe' => 'required|in:kelas,lab,kantor,aula,gudang,lainnya',
            'kapasitas' => 'required|integer|min:0',
            'ada_ac' => 'boolean',
            'ada_proyektor' => 'boolean',
            'ada_wifi' => 'boolean',
            'status' => 'required|in:aktif,maintenance,nonaktif',
        ];
    }
}
