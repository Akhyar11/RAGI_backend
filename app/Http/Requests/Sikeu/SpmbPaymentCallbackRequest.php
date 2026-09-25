<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class SpmbPaymentCallbackRequest extends FormRequest
{
    /**
     * Webhook dilindungi middleware payment.callback (x-callback-token);
     * endpoint simulasi dilindungi auth:api + kepemilikan tagihan.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|string',
            'nominal' => 'required|numeric|min:1000',
            'status' => 'required|in:paid,success,settlement',
            'bank_kode' => 'nullable|string',
            'channel' => 'nullable|string',
        ];
    }
}
