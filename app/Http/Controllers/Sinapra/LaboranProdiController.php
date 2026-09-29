<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\LaboranProdi;
use App\Http\Requests\Sinapra\AssignLaboranProdiRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LaboranProdiController extends Controller
{
    /**
     * Menampilkan daftar penugasan laboran per Program Studi.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Ruangan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = LaboranProdi::with(['user.pegawai', 'programStudi.fakultas']);

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('username', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('programStudi', function ($pq) use ($search) {
                    $pq->where('nama', 'like', "%{$search}%")
                       ->orWhere('kode_prodi', 'like', "%{$search}%");
                });
            });
        }

        $allowedSort = ['created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar penugasan laboran program studi berhasil diambil',
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
                'program_studi_id' => $request->program_studi_id,
                'user_id' => $request->user_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Menugaskan laboran ke Program Studi.
     */
    public function store(AssignLaboranProdiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('sinapra.laboran.manage')) {
            abort(403, 'Anda tidak memiliki hak akses untuk menugaskan laboran program studi.');
        }

        $validated = $request->validated();

        $assignment = LaboranProdi::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'program_studi_id' => $validated['program_studi_id'],
            ],
            [
                'is_primary' => $validated['is_primary'] ?? true,
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'sinapra_laboran_prodi',
                recordId: $assignment->id,
                newValues: $assignment->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Log::warning('Gagal mencatat audit log penugasan laboran prodi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Laboran berhasil ditugaskan ke program studi',
            'data' => $assignment->load(['user.pegawai', 'programStudi']),
        ], 201);
    }

    /**
     * Menghapus penugasan laboran dari Program Studi.
     */
    public function destroy(Request $request, LaboranProdi $laboranProdi): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('sinapra.laboran.manage')) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus penugasan laboran program studi.');
        }

        $oldValues = $laboranProdi->toArray();
        $laboranProdi->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'sinapra_laboran_prodi',
                recordId: $laboranProdi->id,
                oldValues: $oldValues,
                request: $request
            );
        } catch (\Throwable $e) {
            \Log::warning('Gagal mencatat audit log penghapusan laboran prodi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan laboran program studi berhasil dihapus',
            'data' => null,
        ]);
    }
}
