<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\PresensiPengajaranIndexRequest;
use App\Http\Requests\Simpeg\RevokePresensiPengajaranExceptionRequest;
use App\Http\Requests\Simpeg\StorePresensiPengajaranExceptionRequest;
use App\Http\Requests\Simpeg\UpdatePresensiPengajaranCutoffRequest;
use App\Models\Simpeg\PresensiPengajaranException;
use App\Services\Simpeg\PresensiPengajaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PresensiPengajaranController extends Controller
{
    public function __construct(private readonly PresensiPengajaranService $service) {}

    public function index(PresensiPengajaranIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $rows = $this->service->report($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Monitoring presensi pengajaran berhasil dimuat.',
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => [
                'periode' => $filters['periode'],
                'search' => $filters['search'] ?? '',
                'sort_by' => $filters['sort_by'] ?? 'nama_lengkap',
                'sort_order' => $filters['sort_order'] ?? 'asc',
            ],
        ]);
    }

    public function cutoff(PresensiPengajaranIndexRequest $request): JsonResponse
    {
        Gate::authorize('simpeg.presensi.pengajaran.read');
        $period = $request->validated('periode');
        $cutoff = $this->service->cutoff($period);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan cut-off berhasil dimuat.',
            'data' => [
                'periode' => $period,
                'cutoff_at' => $cutoff?->cutoff_at?->toIso8601String(),
                'is_closed' => $cutoff !== null && now()->gte($cutoff->cutoff_at),
            ],
        ]);
    }

    public function updateCutoff(UpdatePresensiPengajaranCutoffRequest $request): JsonResponse
    {
        $cutoff = $this->service->saveCutoff(
            $request->validated('periode'),
            $request->validated('cutoff_at'),
            (int) $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Cut-off presensi pengajaran berhasil disimpan.',
            'data' => [
                'periode' => $cutoff->periode,
                'cutoff_at' => $cutoff->cutoff_at?->toIso8601String(),
                'is_closed' => now()->gte($cutoff->cutoff_at),
            ],
        ]);
    }

    public function exceptions(PresensiPengajaranIndexRequest $request): JsonResponse
    {
        Gate::authorize('simpeg.presensi.pengajaran.read');
        $filters = $request->validated();
        $rows = $this->service->exceptions($filters['periode'], $filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar izin khusus berhasil dimuat.',
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => [
                'periode' => $filters['periode'],
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    public function storeException(StorePresensiPengajaranExceptionRequest $request): JsonResponse
    {
        $exception = $this->service->grantException(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Izin khusus pengisian presensi berhasil diberikan.',
            'data' => $exception->load(['dosen:id,nama_lengkap,nidn,user_id', 'grantor:id,name']),
        ], 201);
    }

    public function revokeException(
        RevokePresensiPengajaranExceptionRequest $request,
        PresensiPengajaranException $exception
    ): JsonResponse {
        $updated = $this->service->revokeException(
            $exception,
            $request->validated('reason'),
            (int) $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Izin khusus pengisian presensi berhasil dicabut.',
            'data' => $updated,
        ]);
    }
}
