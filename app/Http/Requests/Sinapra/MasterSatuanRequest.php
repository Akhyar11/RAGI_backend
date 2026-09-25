<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class MasterSatuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('satuan') ? $this->route('satuan')->id : null;

        return [
            'kode' => 'required|string|max:50|unique:sinapra_master_satuan,kode,' . $id,
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'is_active' => 'boolean',
            'urutan' => 'integer|min:0',
        ];
    }
}
