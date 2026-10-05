<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterTipeRuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? ($this->route('tipe_ruangan') instanceof \App\Models\Sinapra\MasterTipeRuangan ? $this->route('tipe_ruangan')->id : $this->route('tipe_ruangan'));

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_master_tipe_ruangan', 'kode')->ignore($id)->whereNull('deleted_at'),
            ],
            'nama' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'urutan' => 'integer|min:0',
        ];
    }
}
