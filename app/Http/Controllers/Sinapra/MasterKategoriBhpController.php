<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\MasterKategoriBhpRequest;
use App\Services\Sinapra\MasterKategoriBhpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MasterKategoriBhpController extends Controller
{
    public function __construct(
        protected MasterKategoriBhpService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $params = $request->only(['search', 'is_active', 'sort_by', 'sort_order', 'per_page', 'page', 'all']);
        $result = $this->service->getAll($params);

        if ($result instanceof LengthAwarePaginator) {
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar master kategori BHP berhasil diambil',
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                ],
                'filters' => [
                    'search' => $request->query('search'),
                    'is_active' => $request->query('is_active'),
                    'sort_by' => $request->query('sort_by', 'urutan'),
                    'sort_order' => $request->query('sort_order', 'asc'),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar master kategori BHP berhasil diambil',
            'data' => $result,
        ]);
    }

    public function store(MasterKategoriBhpRequest $request): JsonResponse
    {
        $kategori = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori BHP berhasil ditambahkan',
            'data' => $kategori,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $kategori = $this->service->getById($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail master kategori BHP berhasil diambil',
            'data' => $kategori,
        ]);
    }

    public function update(MasterKategoriBhpRequest $request, int $id): JsonResponse
    {
        $kategori = $this->service->update($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori BHP berhasil diperbarui',
            'data' => $kategori,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Master kategori BHP berhasil dihapus',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
