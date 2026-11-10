<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class MidtransCallbackRequest extends FormRequest
{
    /**
     * Webhook Midtrans dipanggil oleh server Midtrans dan diamankan dengan verifikasi Signature Key SHA512.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|string',
            'status_code' => 'required|string',
            'gross_amount' => 'required',
            'signature_key' => 'required|string',
            'transaction_status' => 'required|string',
            'transaction_id' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'fraud_status' => 'nullable|string',
            'transaction_time' => 'nullable|string',
            'settlement_time' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Field order_id wajib diisi oleh webhook Midtrans.',
            'status_code.required' => 'Field status_code wajib diisi.',
            'gross_amount.required' => 'Field gross_amount wajib diisi.',
            'signature_key.required' => 'Field signature_key wajib disertakan untuk verifikasi keamanan.',
            'transaction_status.required' => 'Field transaction_status wajib disertakan.',
        ];
    }
}
