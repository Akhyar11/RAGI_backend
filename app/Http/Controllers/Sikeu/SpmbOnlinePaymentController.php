<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Services\Sikeu\SpmbOnlineSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpmbOnlinePaymentController extends Controller
{
    /** Public webhook, authenticated by the provider callback token in the service. */
    public function xendit(Request $request, SpmbOnlineSettlementService $service): JsonResponse
    {
        $outcome = $service->xendit($request->all(), (string) $request->header('x-callback-token'));

        return response()->json(['status' => 'success', 'message' => 'Notifikasi pembayaran Xendit diterima.', 'data' => $outcome]);
    }
}
