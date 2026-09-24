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
            'gedung_id' => 'required|exists:sinapra_gedung,id',
            'tipe_ruangan_id' => 'nullable|exists:sinapra_master_tipe_ruangan,id',
            'kode' => 'required|string|max:50|unique:sinapra_ruangan,kode,' . $ruanganId,
            'nama' => 'required|string|max:150',
            'lantai' => 'required|integer|min:1',
            'tipe' => 'nullable|string|max:50',
            'kapasitas' => 'required|integer|min:0',
            'ada_ac' => 'boolean',
            'ada_proyektor' => 'boolean',
            'ada_wifi' => 'boolean',
            'status' => 'required|string|max:50',
        ];
    }
}
