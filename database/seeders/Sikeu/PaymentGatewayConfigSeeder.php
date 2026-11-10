<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\PaymentGatewayConfig;

/**
 * Seeder Konfigurasi Default Payment Gateway SIKEU
 * Mengisi tabel sikeu_payment_gateway_configs untuk Midtrans, Xendit, BSN H2H, dan Duitku.
 * Idempoten: aman dijalankan berulang kali tanpa menimpa kredensial produksi yang sudah ada.
 */
class PaymentGatewayConfigSeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'gateway_name' => 'midtrans',
                'environment' => env('MIDTRANS_IS_PRODUCTION', false) ? 'production' : 'sandbox',
                'api_key_encrypted' => env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-DemoKey1234567890'),
                'public_key_encrypted' => env('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-DemoKey1234567890'),
                'webhook_token_encrypted' => env('MIDTRANS_MERCHANT_ID', 'G123456789'),
                'is_active' => true,
                'auto_disbursement_enabled' => false,
                'account_validation_enabled' => true,
                'max_disbursement_limit' => 50000000,
                'va_fee' => 4000,
                'vat_percent' => 11,
                'charge_fee_to_payer' => true,
            ],
            [
                'gateway_name' => 'xendit',
                'environment' => 'sandbox',
                'api_key_encrypted' => env('XENDIT_SECRET_KEY', 'xnd_development_dummy_key'),
                'public_key_encrypted' => env('XENDIT_PUBLIC_KEY', 'xnd_public_dummy_key'),
                'webhook_token_encrypted' => env('XENDIT_WEBHOOK_VERIFICATION_TOKEN', 'xnd_wh_verification_token'),
                'is_active' => false,
                'auto_disbursement_enabled' => false,
                'account_validation_enabled' => true,
                'max_disbursement_limit' => 50000000,
                'va_fee' => 4000,
                'vat_percent' => 11,
                'charge_fee_to_payer' => true,
            ],
            [
                'gateway_name' => 'bsn_h2h',
                'environment' => 'sandbox',
                'base_url' => env('H2H_BTNS_BASE_URL', 'http://127.0.0.1:3002'),
                'server_location' => 'Server Kampus (Local Bridge)',
                'is_active' => true,
                'auto_disbursement_enabled' => false,
                'account_validation_enabled' => false,
                'max_disbursement_limit' => 0,
                'va_fee' => 0,
                'vat_percent' => 0,
                'charge_fee_to_payer' => false,
            ],
            [
                'gateway_name' => 'duitku',
                'environment' => 'sandbox',
                'api_key_encrypted' => '',
                'public_key_encrypted' => '',
                'webhook_token_encrypted' => '',
                'is_active' => false,
                'auto_disbursement_enabled' => false,
                'account_validation_enabled' => false,
                'max_disbursement_limit' => 50000000,
                'va_fee' => 3000,
                'vat_percent' => 11,
                'charge_fee_to_payer' => true,
            ],
        ];

        foreach ($gateways as $g) {
            PaymentGatewayConfig::firstOrCreate(
                ['gateway_name' => $g['gateway_name']],
                $g
            );
        }
    }
}
