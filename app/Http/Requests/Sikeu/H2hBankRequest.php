<?php

namespace App\Http\Requests\Sikeu;

use App\Models\Sikeu\PaymentGatewayConfig;
use Illuminate\Foundation\Http\FormRequest;

class H2hBankRequest extends FormRequest
{
    /**
     * Otorisasi request H2H Bank:
     * Request wajib membawa API token bank yang valid (Bearer token atau header sopingi-btns).
     */
    public function authorize(): bool
    {
        $expectedToken = $this->resolveExpectedToken();

        if (empty($expectedToken)) {
            $expectedToken = app()->environment(['local', 'testing']) ? 'test-btns-token' : null;
        }

        $bearerToken = $this->bearerToken();
        $customHeader = $this->header('sopingi-btns');
        $provided = $bearerToken ?: $customHeader;

        return !empty($expectedToken) && !empty($provided) && hash_equals($expectedToken, (string) $provided);
    }

    protected function failedAuthorization()
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'API Token Bank Tidak Valid atau Tidak Ditemukan',
            'rc' => '401',
            'msg' => 'API Token Bank Tidak Valid atau Tidak Ditemukan',
        ], 401));
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:inquiry,payment,reversal,INQUIRY,PAYMENT,REVERSAL',
            'kodeBank' => 'required|string',
            'nomorPembayaran' => 'required|string',
            'tanggalTransaksi' => 'required|string',
            'checksum' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Field action wajib diisi (inquiry/payment/reversal).',
            'kodeBank.required' => 'Field kodeBank wajib diisi.',
            'nomorPembayaran.required' => 'Field nomorPembayaran wajib diisi.',
            'tanggalTransaksi.required' => 'Field tanggalTransaksi wajib diisi.',
            'checksum.required' => 'Field checksum wajib diisi.',
        ];
    }

    protected function resolveExpectedToken(): ?string
    {
        try {
            $config = PaymentGatewayConfig::where('gateway_name', 'bsn_h2h')->first();
            if ($config && !empty($config->api_key_encrypted)) {
                return (string) $config->api_key_encrypted;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return config('services.sikeu.btns_api_token');
    }
}
