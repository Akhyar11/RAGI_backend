<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class CairkanReferralPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('sikeu.pengeluaran.read');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_kas_id' => ['required', 'integer', 'exists:sikeu_unit_kas,id'],
            'nominal_cair' => ['required', 'numeric', 'min:1'],
            'akun_beban_id' => ['nullable', 'integer', 'exists:sikeu_akun_keuangan,id'],
            'tanggal_bayar' => ['nullable', 'date'],
            'nomor_referensi_transfer' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
