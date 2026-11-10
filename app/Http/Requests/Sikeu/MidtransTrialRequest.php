<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class MidtransTrialRequest extends FormRequest
{
    /**
     * Otorisasi Trial Simulator: Hanya Super Admin atau yang memiliki izin kelola payment gateway.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('sikeu.paymentgateway.manage');
    }

    public function rules(): array
    {
        return [
            'tagihan_id' => 'nullable|integer|exists:sikeu_tagihan_mahasiswa,id',
            'nominal' => 'nullable|numeric|min:1000',
            'payment_type' => 'nullable|string|in:qris,gopay,shopeepay,bank_transfer,bca_va,bni_va,bri_va',
            'fraud_status' => 'nullable|string|in:accept,challenge,deny',
        ];
    }

    public function messages(): array
    {
        return [
            'tagihan_id.exists' => 'Tagihan yang dipilih tidak ditemukan pada sistem.',
            'nominal.min' => 'Nominal simulasi minimal Rp 1.000.',
            'payment_type.in' => 'Tipe pembayaran simulasi tidak didukung.',
        ];
    }
}
