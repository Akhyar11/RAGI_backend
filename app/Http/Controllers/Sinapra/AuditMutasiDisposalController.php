<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\ApproveDisposalAsetRequest;
use App\Http\Requests\Sinapra\ApproveMutasiAsetRequest;
use App\Http\Requests\Sinapra\CreateDisposalAsetRequest;
use App\Http\Requests\Sinapra\CreateMutasiAsetRequest;
use App\Http\Requests\Sinapra\CreateStockOpnameRequest;
use App\Http\Requests\Sinapra\UpdateStockOpnameItemRequest;
use App\Services\Sinapra\AuditMutasiDisposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditMutasiDisposalController extends Controller
{
    public function __construct(
        protected AuditMutasiDisposalService $service
    ) {}

    // ─────────────────────────────────────────────────────────────
    // 1. STOCK OPNAME
    // ─────────────────────────────────────────────────────────────

    public function indexStockOpname(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.opname.read')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'ruangan_id', 'status', 'sort_by', 'sort_dir']);

        $paginator = $this->service->getStockOpnameList($filters, $request->user(), $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar audit stock opname berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? null,
                'ruangan_id' => $filters['ruangan_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort_by' => $filters['sort_by'] ?? 'created_at',
                'sort_dir' => $filters['sort_dir'] ?? 'desc',
            ],
        ]);
    }

    public function showStockOpname(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.opname.read')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $opname = $this->service->getStockOpnameDetail($id, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Detail sesi stock opname berhasil diambil.',
            'data' => $opname,
        ]);
    }

    public function storeStockOpname(CreateStockOpnameRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.opname.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $opname = $this->service->createStockOpname($request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi stock opname berhasil dibuat.',
            'data' => $opname,
        ], 201);
    }

    public function updateStockOpnameItem(UpdateStockOpnameItemRequest $request, int $id, int $itemId): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.opname.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $item = $this->service->updateStockOpnameItem($id, $itemId, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Pemeriksaan fisik aset berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    public function finishStockOpname(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.opname.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $request->validate([
            'catatan' => ['nullable', 'string'],
        ]);

        $opname = $this->service->finishStockOpname($id, $request->only('catatan'), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi stock opname berhasil diselesaikan dan status fisik aset telah disinkronkan.',
            'data' => $opname,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. MUTASI ASET ANTAR-RUANGAN
    // ─────────────────────────────────────────────────────────────

    public function indexMutasi(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.mutasi.read')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'ruangan_asal_id', 'ruangan_tujuan_id', 'status', 'sort_by', 'sort_dir']);

        $paginator = $this->service->getMutasiList($filters, $request->user(), $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan mutasi aset berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? null,
                'ruangan_asal_id' => $filters['ruangan_asal_id'] ?? null,
                'ruangan_tujuan_id' => $filters['ruangan_tujuan_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort_by' => $filters['sort_by'] ?? 'created_at',
                'sort_dir' => $filters['sort_dir'] ?? 'desc',
            ],
        ]);
    }

    public function storeMutasi(CreateMutasiAsetRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.mutasi.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $mutasi = $this->service->createMutasi($request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan mutasi aset berhasil diajukan.',
            'data' => $mutasi,
        ], 201);
    }

    public function approveMutasi(ApproveMutasiAsetRequest $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.mutasi.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $mutasi = $this->service->approveMutasi($id, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan mutasi aset berhasil diproses.',
            'data' => $mutasi,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. PENGHAPUSAN / DISPOSAL ASET
    // ─────────────────────────────────────────────────────────────

    public function indexDisposal(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.disposal.read')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'metode_disposal', 'status', 'sort_by', 'sort_dir']);

        $paginator = $this->service->getDisposalList($filters, $request->user(), $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar penghapusan aset berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? null,
                'metode_disposal' => $filters['metode_disposal'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort_by' => $filters['sort_by'] ?? 'created_at',
                'sort_dir' => $filters['sort_dir'] ?? 'desc',
            ],
        ]);
    }

    public function storeDisposal(CreateDisposalAsetRequest $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.disposal.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $disposal = $this->service->createDisposal($request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan pemutihan / penghapusan aset berhasil dicatat.',
            'data' => $disposal,
        ], 201);
    }

    public function approveDisposal(ApproveDisposalAsetRequest $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('sinapra.disposal.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $disposal = $this->service->approveDisposal($id, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Keputusan usulan penghapusan aset berhasil diproses.',
            'data' => $disposal,
        ]);
    }
}
