<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use App\Services\Sinapra\MaintenanceService;
use App\Http\Requests\Sinapra\MaintenanceLogRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceLog::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = MaintenanceLog::with(['aset', 'ruangan', 'teknisi']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('prioritas')) {
            $query->where('prioritas', $request->prioritas);
        }

        if ($request->filled('aset_id')) {
            $query->where('aset_id', $request->aset_id);
        }

        if ($request->filled('ruangan_id')) {
            $query->where('ruangan_id', $request->ruangan_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi_kerusakan', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'tanggal_lapor', 'prioritas', 'status', 'biaya'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar maintenance log berhasil diambil',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'prioritas' => $request->prioritas,
                'aset_id' => $request->aset_id,
                'ruangan_id' => $request->ruangan_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(MaintenanceLogRequest $request): JsonResponse
    {
        $this->authorize('create', MaintenanceLog::class);

        $log = $this->service->createMaintenanceLog($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket maintenance berhasil dibuat',
            'data' => $log->load(['aset', 'ruangan', 'teknisi']),
        ], 201);
    }

    public function show(MaintenanceLog $maintenance): JsonResponse
    {
        $this->authorize('view', $maintenance);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tiket maintenance berhasil diambil',
            'data' => $maintenance->load(['aset', 'ruangan', 'teknisi']),
        ]);
    }

    public function update(MaintenanceLogRequest $request, MaintenanceLog $maintenance): JsonResponse
    {
        $this->authorize('update', $maintenance);

        $updated = $this->service->updateMaintenanceStatus($maintenance, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket maintenance berhasil diperbarui',
            'data' => $updated->load(['aset', 'ruangan', 'teknisi']),
        ]);
    }

    public function destroy(MaintenanceLog $maintenance): JsonResponse
    {
        $this->authorize('delete', $maintenance);

        $this->service->deleteMaintenanceLog($maintenance);

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket maintenance berhasil dihapus',
            'data' => null,
        ]);
    }
}
