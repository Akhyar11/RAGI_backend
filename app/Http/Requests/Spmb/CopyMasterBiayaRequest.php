<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class CopyMasterBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'from_gelombang_id' => 'required|exists:spmb_gelombang_penerimaan,id',
            'to_gelombang_id' => 'required|exists:spmb_gelombang_penerimaan,id|different:from_gelombang_id',
        ];
    }
}
