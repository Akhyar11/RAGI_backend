<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi dibedakan per gateway:
     * - bsn_h2h: koneksi bridge H2H BTN Syariah (URL API + lokasi server + DB bridge).
     * - lainnya (xendit/duitku): kredensial API seperti sebelumnya.
     */
    public function rules(): array
    {
        $gateway = strtolower((string) $this->route('gatewayName'));

        $base = [
            'environment' => 'required|in:sandbox,production',
            'is_active' => 'required|boolean',
        ];

        if ($gateway === 'bsn_h2h') {
            return $base + [
                'base_url' => 'required|url|max:255',
                'server_location' => 'nullable|string|max:255',
                'api_key' => 'nullable|string',
                'public_key' => 'nullable|string',
                'webhook_token' => 'nullable|string',
                'db_host' => 'nullable|string|max:100',
                'db_port' => 'nullable|integer|min:1|max:65535',
                'db_name' => 'nullable|string|max:100',
                'db_username' => 'nullable|string|max:100',
                'db_password' => 'nullable|string',
                'auto_disbursement_enabled' => 'nullable|boolean',
                'account_validation_enabled' => 'nullable|boolean',
                'max_disbursement_limit' => 'nullable|numeric|min:0',
            ];
        }

        return $base + [
            'api_key' => 'required|string',
            'public_key' => 'nullable|string',
            'webhook_token' => 'nullable|string',
            'auto_disbursement_enabled' => 'required|boolean',
            'account_validation_enabled' => 'required|boolean',
            'max_disbursement_limit' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'base_url.required' => 'URL API bridge H2H wajib diisi.',
            'base_url.url' => 'URL API bridge H2H tidak valid (contoh: http://192.168.1.10:3002).',
        ];
    }
}
