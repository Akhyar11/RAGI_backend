<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ValidateSpmbKodeDiskonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('kode')) {
            $this->merge(['kode' => Str::upper(trim((string) $this->input('kode')))]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.pendaftaran.create');
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50'],
            'master_tipe_jalur_id' => ['required', 'integer', 'exists:core_master_tipe_jalur,id'],
            'program_studi_id' => ['required', 'integer', 'exists:siakad_program_studi,id'],
            'gelombang_id' => ['required', 'integer', 'exists:spmb_gelombang_penerimaan,id'],
        ];
    }
}
