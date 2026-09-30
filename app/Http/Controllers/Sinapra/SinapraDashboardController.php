<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Services\Sinapra\SinapraDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SinapraDashboardController extends Controller
{
    public function __construct(
        protected SinapraDashboardService $dashboardService
    ) {}

    /**
     * GET /api/sinapra/dashboard-summary
     * Menyajikan ringkasan metrik eksekutif, ketersediaan ruangan, nilai finansial aset,
     * early warnings laboratorium, serta log aktivitas terbaru sarana dan prasarana.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->dashboardService->getSummary();

            return response()->json([
                'status' => 'success',
                'message' => 'Ringkasan dashboard SINAPRA berhasil dimuat',
                'data' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat ringkasan dashboard SINAPRA: ' . $e->getMessage(),
            ], 500);
        }
    }
}
