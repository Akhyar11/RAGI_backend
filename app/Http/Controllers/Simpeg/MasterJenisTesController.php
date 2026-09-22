<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterJenisTesRequest;
use App\Http\Requests\Simpeg\UpdateMasterJenisTesRequest;
use App\Models\Simpeg\MasterJenisTes;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterJenisTesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.kompetensi.read') && 
            !$user->hasPermission('simpeg.kompetensi.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master jenis tes.'
        );

        $query = MasterJenisTes::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['created_at', 'updated_at', 'id', 'nama', 'kode', 'kategori', 'skor_min', 'skor_max'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
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
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(StoreMasterJenisTesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $data = MasterJenisTes::create($validated);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_master_jenis_tes',
                recordId: $data->id,
                oldValues: [],
                newValues: $data->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis tes berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.kompetensi.read') && 
            !$user->hasPermission('simpeg.kompetensi.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master jenis tes.'
        );

        $data = MasterJenisTes::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data,
        ]);
    }

    public function update(UpdateMasterJenisTesRequest $request, int $id): JsonResponse
    {
        $data = MasterJenisTes::findOrFail($id);
        $oldValues = $data->getOriginal();
        $data->update($request->validated());

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_master_jenis_tes',
                recordId: $data->id,
                oldValues: $oldValues,
                newValues: $data->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis tes berhasil diperbarui',
            'data' => $data,
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk menghapus master jenis tes.'
        );

        $data = MasterJenisTes::withCount('riwayatTes')->findOrFail($id);

        if ($data->riwayat_tes_count > 0) {
            throw ValidationException::withMessages([
                'id' => ['Data jenis tes masih digunakan pada data riwayat tes pegawai.'],
            ]);
        }

        $oldValues = $data->getOriginal();
        $data->delete();

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_master_jenis_tes',
                recordId: (int) $id,
                oldValues: $oldValues,
                newValues: [],
                request: $request
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis tes berhasil dihapus',
            'data' => [
                'id' => $id,
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
