<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\MasterTipeRuanganRequest;
use App\Models\Sinapra\MasterTipeRuangan;
use App\Services\Sinapra\MasterTipeRuanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterTipeRuanganController extends Controller
{
    protected MasterTipeRuanganService $service;

    public function __construct(MasterTipeRuanganService $service)
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
                'message' => 'Daftar semua tipe ruangan berhasil diambil',
                'data' => $result,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tipe ruangan berhasil diambil',
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

    public function store(MasterTipeRuanganRequest $request): JsonResponse
    {
        $tipeRuangan = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe ruangan berhasil ditambahkan',
            'data' => $tipeRuangan,
        ], 201);
    }

    public function show(MasterTipeRuangan $tipeRuangan): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Detail tipe ruangan berhasil diambil',
            'data' => $tipeRuangan->loadCount('ruangan'),
        ]);
    }

    public function update(MasterTipeRuanganRequest $request, MasterTipeRuangan $tipeRuangan): JsonResponse
    {
        $updated = $this->service->update($tipeRuangan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe ruangan berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function destroy(MasterTipeRuangan $tipeRuangan): JsonResponse
    {
        if ($tipeRuangan->ruangan()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tipe ruangan tidak dapat dihapus karena masih digunakan oleh data ruangan kampus',
            ], 422);
        }

        $this->service->delete($tipeRuangan);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe ruangan berhasil dihapus',
            'data' => null,
        ]);
    }
}
