<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class CreatePayoutReferralRequest extends FormRequest
{
    /**
     * Pengguna hanya dapat membuat bukti pencairan untuk referral miliknya sendiri.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'keterangan' => 'nullable|string|max:255',
            'nama_bank' => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:60',
            'nama_pemilik_rekening' => 'required|string|max:150',
        ];
    }
}
