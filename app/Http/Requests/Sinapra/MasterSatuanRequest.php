<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterSatuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? ($this->route('satuan') instanceof \App\Models\Sinapra\MasterSatuan ? $this->route('satuan')->id : $this->route('satuan'));

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_master_satuan', 'kode')->ignore($id)->whereNull('deleted_at'),
            ],
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'is_active' => 'boolean',
            'urutan' => 'integer|min:0',
        ];
    }
}
