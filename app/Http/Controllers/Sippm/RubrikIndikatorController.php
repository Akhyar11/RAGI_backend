<?php

namespace App\Http\Controllers\Sippm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sippm\StoreRubrikIndikatorRequest;
use App\Http\Requests\Sippm\UpdateRubrikIndikatorRequest;
use App\Models\Sippm\RubrikIndikator;
use App\Services\Sippm\RubrikIndikatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RubrikIndikatorController extends Controller
{
    protected $rubrikService;

    public function __construct(RubrikIndikatorService $rubrikService)
    {
        $this->rubrikService = $rubrikService;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $filters = [
            'search' => $request->query('search'),
            'tipe_reviewer' => $request->query('tipe_reviewer'),
            'is_active' => $request->query('is_active'),
            'sort_by' => $request->query('sort_by'),
            'sort_order' => $request->query('sort_order'),
        ];

        $result = $this->rubrikService->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data rubrik indikator berhasil diambil',
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
                'from' => $result->firstItem(),
                'to' => $result->lastItem(),
            ],
            'filters' => $filters,
        ]);
    }

    public function store(StoreRubrikIndikatorRequest $request): JsonResponse
    {
        $rubrik = $this->rubrikService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik indikator berhasil dibuat',
            'data' => $rubrik,
        ], 201);
    }

    public function show(RubrikIndikator $rubrik): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Detail rubrik indikator berhasil diambil',
            'data' => $rubrik,
        ]);
    }

    public function update(UpdateRubrikIndikatorRequest $request, RubrikIndikator $rubrik): JsonResponse
    {
        $updated = $this->rubrikService->update($rubrik, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik indikator berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function destroy(RubrikIndikator $rubrik): JsonResponse
    {
        $this->rubrikService->delete($rubrik);

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik indikator berhasil dihapus',
        ]);
    }
}
