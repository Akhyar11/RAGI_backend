<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'master_tipe_jalur_id' => 'required|exists:core_master_tipe_jalur,id',
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
            'items' => 'required|array',
            'items.*.komponen_biaya_id' => 'required|exists:spmb_master_komponen_biaya,id',
            'items.*.nominal' => 'required|numeric|min:0',
            'items.*.dibebankan_saat_pendaftaran' => 'nullable|boolean',
            'items.*.berlaku_diskon' => 'nullable|boolean',
            'items.*.keterangan' => 'nullable|string|max:255',
        ];
    }
}
