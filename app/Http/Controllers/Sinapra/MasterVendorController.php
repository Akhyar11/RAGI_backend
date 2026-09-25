<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\MasterVendorRequest;
use App\Services\Sinapra\MasterVendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MasterVendorController extends Controller
{
    public function __construct(
        protected MasterVendorService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $params = $request->only(['search', 'jenis_rekanan', 'is_active', 'sort_by', 'sort_order', 'per_page', 'page', 'all']);
        $result = $this->service->getAll($params);

        if ($result instanceof LengthAwarePaginator) {
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar master vendor berhasil diambil',
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
                    'jenis_rekanan' => $request->query('jenis_rekanan'),
                    'is_active' => $request->query('is_active'),
                    'sort_by' => $request->query('sort_by', 'urutan'),
                    'sort_order' => $request->query('sort_order', 'asc'),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar master vendor berhasil diambil',
            'data' => $result,
        ]);
    }

    public function store(MasterVendorRequest $request): JsonResponse
    {
        $vendor = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master vendor berhasil ditambahkan',
            'data' => $vendor,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $vendor = $this->service->getById($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail master vendor berhasil diambil',
            'data' => $vendor,
        ]);
    }

    public function update(MasterVendorRequest $request, int $id): JsonResponse
    {
        $vendor = $this->service->update($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master vendor berhasil diperbarui',
            'data' => $vendor,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Master vendor berhasil dihapus',
            'data' => null,
        ]);
    }
}
