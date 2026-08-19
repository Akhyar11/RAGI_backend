<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sippm\StoreStandarIku5ProdiRequest;
use App\Http\Requests\Sippm\UpdateStandarIku5ProdiRequest;
use App\Models\Sippm\StandarIku5Prodi;
use App\Services\Sippm\StandarIku5ProdiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandarIku5ProdiController extends Controller
{
    public function __construct(
        protected StandarIku5ProdiService $standarService
    ) {}

    /**
     * Display a listing of IKU 5 standards.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'sort_by', 'sort_order', 'tahun_akademik', 'unit_kerja_id']);

        $data = $this->standarService->getPaginatedList($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data standar IKU 5 prodi berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Store or upsert a new IKU 5 standard.
     */
    public function store(StoreStandarIku5ProdiRequest $request): JsonResponse
    {
        $standar = $this->standarService->upsertStandar($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Standar IKU 5 prodi berhasil disimpan.',
            'data' => $standar,
        ], 201);
    }

    /**
     * Display the specified IKU 5 standard.
     */
    public function show(StandarIku5Prodi $iku5_standard): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Detail standar IKU 5 prodi berhasil diambil.',
            'data' => $iku5_standard->load('unitKerja'),
        ]);
    }

    /**
     * Update the specified IKU 5 standard.
     */
    public function update(UpdateStandarIku5ProdiRequest $request, StandarIku5Prodi $iku5_standard): JsonResponse
    {
        $updated = $this->standarService->updateStandar($iku5_standard, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Standar IKU 5 prodi berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Remove the specified IKU 5 standard.
     */
    public function destroy(StandarIku5Prodi $iku5_standard): JsonResponse
    {
        $this->standarService->deleteStandar($iku5_standard);

        return response()->json([
            'status' => 'success',
            'message' => 'Standar IKU 5 prodi berhasil dihapus.',
        ]);
    }
}
