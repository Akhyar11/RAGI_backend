<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKomponenBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'kode' => 'required|string|max:50|unique:spmb_master_komponen_biaya,kode,' . $id,
            'nama' => 'required|string|max:150',
            'kategori' => 'nullable|string|max:50',
            'tipe_potongan' => 'nullable|boolean',
            'is_referral_reward' => 'nullable|boolean',
            'role_rewards' => 'nullable|array',
            'role_rewards.*.role_id' => 'required|integer|exists:core_roles,id',
            'role_rewards.*.nominal' => 'required|numeric|min:0',
            'urutan' => 'nullable|integer',
            'position_type' => 'nullable|string|max:20',
            'reference_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }
}
