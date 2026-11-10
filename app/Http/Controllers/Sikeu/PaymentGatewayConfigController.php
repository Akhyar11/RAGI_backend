<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\UpdatePaymentGatewayConfigRequest;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayConfigController extends Controller
{
    /**
     * Get all Payment Gateway configs
     */
    public function index()
    {
        $configs = PaymentGatewayConfig::all();
        return response()->json([
            'status' => 'success',
            'data' => $configs
        ]);
    }

    /**
     * Get the active Payment Gateway
     */
    public function getActive()
    {
        $config = PaymentGatewayConfig::where('is_active', true)->first();
        return response()->json([
            'status' => 'success',
            'data' => $config
        ]);
    }

    /**
     * Update or create a Payment Gateway config.
     *
     * Catatan: 'bsn_h2h' adalah kanal paralel (BTN Syariah), bukan pengganti
     * xendit/duitku — mengaktifkannya TIDAK menonaktifkan gateway lain.
     */
    public function update(UpdatePaymentGatewayConfigRequest $request, $gatewayName)
    {
        $gatewayName = strtolower((string) $gatewayName);
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // H2H berjalan paralel: jangan ubah status gateway lain.
            // Gateway online (xendit/duitku) tetap saling eksklusif satu sama lain.
            if ($request->boolean('is_active') && $gatewayName !== 'bsn_h2h') {
                PaymentGatewayConfig::where('gateway_name', '!=', $gatewayName)
                    ->where('gateway_name', '!=', 'bsn_h2h')
                    ->update(['is_active' => false]);
            }

            $payload = [
                'environment' => $data['environment'],
                'is_active' => $data['is_active'] ?? false,
                'updated_by' => auth()->id() ?? 1,
            ];

            if ($gatewayName === 'bsn_h2h') {
                $payload += [
                    'base_url' => $data['base_url'] ?? null,
                    'server_location' => $data['server_location'] ?? null,
                    'api_key_encrypted' => $data['api_key'] ?? null,
                    'public_key_encrypted' => $data['public_key'] ?? null,
                    'webhook_token_encrypted' => $data['webhook_token'] ?? null,
                    'db_host' => $data['db_host'] ?? null,
                    'db_port' => $data['db_port'] ?? 3306,
                    'db_name' => $data['db_name'] ?? null,
                    'db_username' => $data['db_username'] ?? null,
                    'auto_disbursement_enabled' => $data['auto_disbursement_enabled'] ?? false,
                    'account_validation_enabled' => $data['account_validation_enabled'] ?? false,
                    'max_disbursement_limit' => $data['max_disbursement_limit'] ?? 0,
                ];
                if (array_key_exists('db_password', $data) && $data['db_password'] !== null && $data['db_password'] !== '') {
                    $payload['db_password_encrypted'] = $data['db_password'];
                }
            } else {
                $payload += [
                    'api_key_encrypted' => $data['api_key'],
                    'public_key_encrypted' => $data['public_key'] ?? null,
                    'webhook_token_encrypted' => $data['webhook_token'] ?? null,
                    'auto_disbursement_enabled' => $data['auto_disbursement_enabled'],
                    'account_validation_enabled' => $data['account_validation_enabled'],
                    'max_disbursement_limit' => $data['max_disbursement_limit'],
                    'va_fee' => $data['va_fee'] ?? 0,
                    'vat_percent' => $data['vat_percent'] ?? 11,
                    'charge_fee_to_payer' => $data['charge_fee_to_payer'] ?? false,
                ];
            }

            $config = PaymentGatewayConfig::firstOrNew(['gateway_name' => $gatewayName]);
            $oldValues = $config->exists ? $config->getOriginal() : [];
            $isNew = !$config->exists;

            $config->fill($payload)->save();

            try {
                AuditLogService::record(
                    action: $isNew ? 'create' : 'update',
                    module: 'sikeu',
                    targetType: PaymentGatewayConfig::class,
                    targetId: (string) $config->id,
                    oldData: $oldValues,
                    newData: $config->getChanges() ?: $config->toArray(),
                    description: "Pembaruan konfigurasi Payment Gateway {$gatewayName}"
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log konfigurasi gateway: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Konfigurasi Payment Gateway berhasil disimpan.',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan konfigurasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance for a specific payment gateway.
     * H2H BTN Syariah tidak punya konsep saldo gateway — gunakan GET /h2h/status
     * untuk diagnostik konektivitas bridge.
     */
    public function balance($gatewayName)
    {
        $gatewayName = strtolower((string) $gatewayName);

        if ($gatewayName === 'bsn_h2h' || $gatewayName === 'rekening_manual') {
            return response()->json([
                'status' => 'error',
                'message' => 'H2H BTN Syariah / Rekening Manual tidak memiliki saldo gateway. Gunakan GET /api/v1/sikeu/h2h/status untuk diagnostik bridge.',
            ], 422);
        }

        $config = PaymentGatewayConfig::where('gateway_name', $gatewayName)->first();
        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konfigurasi gateway tidak ditemukan.',
            ], 404);
        }

        $balance = 0;
        $pending = 0;

        try {
            if ($gatewayName === 'xendit') {
                $apiKey = $config->api_key_encrypted;
                if (!$apiKey) {
                    throw new \Exception("API Key Xendit belum dikonfigurasi.");
                }

                $response = Http::withoutVerifying()
                    ->withBasicAuth($apiKey, '')
                    ->get('https://api.xendit.co/balance');

                if ($response->successful()) {
                    $balanceData = $response->json();
                    $balance = $balanceData['balance'] ?? 0;
                } else {
                    throw new \Exception("Gagal menghubungi API Xendit: " . $response->body());
                }
            } else if ($gatewayName === 'midtrans' || $gatewayName === 'duitku') {
                // Midtrans & Duitku melakukan settlement langsung ke rekening bank operasional kampus
                $balance = 0;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'available_balance' => $balance,
                    'pending_settlement' => $pending,
                    'total_balance' => $balance + $pending,
                    'currency' => 'IDR',
                    'last_updated' => now()->format('H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
