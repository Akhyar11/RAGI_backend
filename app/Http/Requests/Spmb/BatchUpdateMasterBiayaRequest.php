<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateMasterBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'gelombang_id' => 'required|exists:spmb_gelombang_penerimaan,id',
            'rows' => 'required|array',
            'rows.*.program_studi_id' => 'required|exists:siakad_program_studi,id',
            'rows.*.is_active' => 'nullable|boolean',
            'rows.*.items' => 'required|array',
            'rows.*.items.*.komponen_biaya_id' => 'required|exists:spmb_master_komponen_biaya,id',
            'rows.*.items.*.nominal' => 'required|numeric|min:0',
            'rows.*.items.*.dibebankan_saat_pendaftaran' => 'nullable|boolean',
        ];
    }
}
