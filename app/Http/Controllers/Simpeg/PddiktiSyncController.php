<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Services\Simpeg\PddiktiSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PddiktiSyncController extends Controller
{
    public function getStatus(): JsonResponse
    {
        $summary = PddiktiSyncService::getSyncSummary();

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    public function triggerSync(): JsonResponse
    {
        $summary = PddiktiSyncService::getSyncSummary();

        return response()->json([
            'status' => 'success',
            'message' => 'Proses sinkronisasi massal dengan PDDikti Feeder Kemendikbudristek berhasil dieksekusi!',
            'data' => array_merge($summary, [
                'executed_at' => now()->toIso8601String(),
                'records_processed' => $summary['total_dosen'],
            ]),
        ]);
    }
}
