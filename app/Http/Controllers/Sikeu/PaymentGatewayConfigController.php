<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PaymentGatewayConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
     * Update or create a Payment Gateway config
     */
    public function update(Request $request, $gatewayName)
    {
        $request->validate([
            'environment' => 'required|in:sandbox,production',
            'api_key' => 'required|string',
            'public_key' => 'nullable|string',
            'webhook_token' => 'nullable|string',
            'is_active' => 'required|boolean',
            'auto_disbursement_enabled' => 'required|boolean',
            'account_validation_enabled' => 'required|boolean',
            'max_disbursement_limit' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // If this one is being set to active, deactivate all others
            if ($request->is_active) {
                PaymentGatewayConfig::where('gateway_name', '!=', $gatewayName)->update(['is_active' => false]);
            }

            $config = PaymentGatewayConfig::updateOrCreate(
                ['gateway_name' => $gatewayName],
                [
                    'environment' => $request->environment,
                    'api_key_encrypted' => $request->api_key,
                    'public_key_encrypted' => $request->public_key,
                    'webhook_token_encrypted' => $request->webhook_token,
                    'is_active' => $request->is_active,
                    'auto_disbursement_enabled' => $request->auto_disbursement_enabled,
                    'account_validation_enabled' => $request->account_validation_enabled,
                    'max_disbursement_limit' => $request->max_disbursement_limit,
                    'updated_by' => auth()->id() ?? 1,
                ]
            );

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
     * Get balance for a specific payment gateway
     */
    public function balance($gatewayName)
    {
        $config = PaymentGatewayConfig::where('gateway_name', $gatewayName)->first();
        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Config not found'], 404);
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
            } else if ($gatewayName === 'duitku') {
                // Duitku has no direct balance API documented in standard ways usually available like this, 
                // but we will return 0 or placeholder if real API doesn't exist, or mock it if requested. 
                // However, since user explicitly mentioned Xendit, we focus on Xendit.
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
