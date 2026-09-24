<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class CreateDisposalAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['required', 'integer', 'exists:sinapra_aset,id'],
            'nomor_bap' => ['nullable', 'string', 'max:100'],
            'tanggal_disposal' => ['nullable', 'date'],
            'metode_disposal' => ['required', 'string', 'in:rusak_total,kadaluwarsa,hilang,hibah,lelang,lainnya'],
            'nilai_residu' => ['nullable', 'numeric', 'min:0'],
            'alasan' => ['required', 'string'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
