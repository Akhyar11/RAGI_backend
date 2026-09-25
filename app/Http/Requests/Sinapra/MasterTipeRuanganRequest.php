<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class MasterTipeRuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tipe_ruangan') ? $this->route('tipe_ruangan')->id : null;

        return [
            'kode' => 'required|string|max:50|unique:sinapra_master_tipe_ruangan,kode,' . $id,
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'urutan' => 'integer|min:0',
        ];
    }
}
