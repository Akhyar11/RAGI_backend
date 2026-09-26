<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\BatchUpdateMasterBiayaRequest;
use App\Http\Requests\Spmb\CopyMasterBiayaRequest;
use App\Http\Requests\Spmb\StoreKomponenBiayaRequest;
use App\Http\Requests\Spmb\StoreMasterBiayaRequest;
use App\Http\Requests\Spmb\UpdateKomponenBiayaRequest;
use App\Http\Requests\Spmb\UpdateMasterBiayaRequest;
use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\MasterProgramStudi;
use App\Services\Spmb\MasterBiayaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterBiayaSpmbController extends Controller
{
    public function __construct(
        private readonly MasterBiayaService $service
    ) {}

    // ==========================================
    // 1. MASTER KOMPONEN BIAYA
    // ==========================================

    public function getKomponen(Request $request): JsonResponse
    {
        $query = MasterKomponenBiaya::query()->with(['roleRewards.role:id,slug,name']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->input('kategori'));
        }

        $allowedSorts = ['created_at', 'updated_at', 'urutan', 'nama', 'kode', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data komponen biaya berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function storeKomponen(StoreKomponenBiayaRequest $request): JsonResponse
    {
        $komponen = $this->service->createKomponen($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen biaya berhasil ditambahkan.',
            'data' => $komponen->load('roleRewards.role:id,slug,name'),
        ], 201);
    }

    public function updateKomponen(UpdateKomponenBiayaRequest $request, $id): JsonResponse
    {
        $komponen = MasterKomponenBiaya::findOrFail($id);
        $updated = $this->service->updateKomponen($komponen, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen biaya berhasil diperbarui.',
            'data' => $updated->load('roleRewards.role:id,slug,name'),
        ]);
    }

    public function showKomponen(Request $request, $id): JsonResponse
    {


        $komponen = MasterKomponenBiaya::with('roleRewards.role:id,slug,name')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail komponen biaya berhasil dimuat.',
            'data' => $komponen,
        ]);
    }

    public function destroyKomponen(Request $request, $id): JsonResponse
    {


        $komponen = MasterKomponenBiaya::findOrFail($id);
        $this->service->deleteKomponen($komponen);

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen biaya berhasil dihapus (soft delete).',
        ]);
    }

    public function restoreKomponen(Request $request, $id): JsonResponse
    {


        $komponen = MasterKomponenBiaya::withTrashed()->findOrFail($id);
        $this->service->restoreKomponen($komponen);

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen biaya berhasil dipulihkan.',
            'data' => $komponen,
        ]);
    }

    // ==========================================
    // 2. MASTER BIAYA SPMB (HEADER & ITEM)
    // ==========================================

    public function index(Request $request): JsonResponse
    {


        $query = MasterBiaya::with(['items.komponenBiaya', 'programStudi', 'gelombang']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('programStudi', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_prodi', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gelombang_id')) {
            $query->where('gelombang_id', $request->input('gelombang_id'));
        }

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->input('program_studi_id'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['created_at', 'updated_at', 'total_biaya', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master biaya berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $biaya = MasterBiaya::with(['items.komponenBiaya', 'programStudi', 'gelombang'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail master biaya berhasil dimuat.',
            'data' => $biaya,
        ]);
    }

    public function store(StoreMasterBiayaRequest $request): JsonResponse
    {
        $masterBiaya = $this->service->createBiaya($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master biaya program studi berhasil disimpan.',
            'data' => $masterBiaya,
        ], 201);
    }

    public function update(UpdateMasterBiayaRequest $request, $id): JsonResponse
    {
        $masterBiaya = MasterBiaya::findOrFail($id);
        $updated = $this->service->updateBiaya($masterBiaya, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master biaya program studi berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {


        $masterBiaya = MasterBiaya::findOrFail($id);
        $this->service->deleteBiaya($masterBiaya);

        return response()->json([
            'status' => 'success',
            'message' => 'Master biaya program studi berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(Request $request, $id): JsonResponse
    {


        $masterBiaya = MasterBiaya::withTrashed()->findOrFail($id);
        $this->service->restoreBiaya($masterBiaya);

        return response()->json([
            'status' => 'success',
            'message' => 'Master biaya program studi berhasil dipulihkan.',
            'data' => $masterBiaya,
        ]);
    }

    public function batchUpdate(BatchUpdateMasterBiayaRequest $request): JsonResponse
    {
        $this->service->batchUpdate($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Semua konfigurasi biaya program studi berhasil disimpan secara massal.',
            'data' => ['updated' => true],
        ]);
    }

    public function copyFromGelombang(CopyMasterBiayaRequest $request): JsonResponse
    {
        $count = $this->service->copyFromGelombang($request->validated());

        if ($count === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ditemukan data biaya pada gelombang sumber.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil menyalin ' . $count . ' data biaya program studi ke gelombang target.',
            'data' => ['total_copied' => $count],
        ]);
    }
}
