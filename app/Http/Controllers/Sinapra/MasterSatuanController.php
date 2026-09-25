<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\MasterSatuanRequest;
use App\Models\Sinapra\MasterSatuan;
use App\Services\Sinapra\MasterSatuanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterSatuanController extends Controller
{
    protected MasterSatuanService $service;

    public function __construct(MasterSatuanService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'sort_by', 'sort_order', 'per_page', 'all']);
        $result = $this->service->getList($filters);

        if ($request->boolean('all')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar semua satuan berhasil diambil',
                'data' => $result,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar satuan barang berhasil diambil',
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

    public function store(MasterSatuanRequest $request): JsonResponse
    {
        $satuan = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Satuan barang berhasil ditambahkan',
            'data' => $satuan,
        ], 201);
    }

    public function show(MasterSatuan $satuan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Detail satuan barang berhasil diambil',
            'data' => $satuan,
        ]);
    }

    public function update(MasterSatuanRequest $request, MasterSatuan $satuan): JsonResponse
    {
        $updated = $this->service->update($satuan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Satuan barang berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function destroy(MasterSatuan $satuan): JsonResponse
    {
        $this->service->delete($satuan);

        return response()->json([
            'status' => 'success',
            'message' => 'Satuan barang berhasil dihapus',
            'data' => null,
        ]);
    }
}
