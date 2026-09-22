<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterTingkatKegiatanRequest;
use App\Http\Requests\Simpeg\UpdateMasterTingkatKegiatanRequest;
use App\Models\Simpeg\MasterTingkatKegiatan;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterTingkatKegiatanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.kompetensi.read') && 
            !$user->hasPermission('simpeg.kompetensi.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master tingkat kegiatan.'
        );

        $query = MasterTingkatKegiatan::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['created_at', 'updated_at', 'id', 'nama'];
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

    public function store(StoreMasterTingkatKegiatanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $data = MasterTingkatKegiatan::create($validated);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_master_tingkat_kegiatan',
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
            'message' => 'Master tingkat kegiatan berhasil ditambahkan',
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
            'Anda tidak memiliki hak akses untuk melihat master tingkat kegiatan.'
        );

        $data = MasterTingkatKegiatan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data,
        ]);
    }

    public function update(UpdateMasterTingkatKegiatanRequest $request, int $id): JsonResponse
    {
        $data = MasterTingkatKegiatan::findOrFail($id);
        $oldValues = $data->getOriginal();
        $data->update($request->validated());

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_master_tingkat_kegiatan',
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
            'message' => 'Master tingkat kegiatan berhasil diperbarui',
            'data' => $data,
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk menghapus master tingkat kegiatan.'
        );

        $data = MasterTingkatKegiatan::withCount('riwayatPelatihan')->findOrFail($id);

        if ($data->riwayat_pelatihan_count > 0) {
            throw ValidationException::withMessages([
                'id' => ['Data tingkat kegiatan masih digunakan pada data riwayat pelatihan pegawai.'],
            ]);
        }

        $oldValues = $data->getOriginal();
        $data->delete();

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_master_tingkat_kegiatan',
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
            'message' => 'Master tingkat kegiatan berhasil dihapus',
            'data' => [
                'id' => $id,
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
