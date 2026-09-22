<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterKategoriSkRequest;
use App\Http\Requests\Simpeg\UpdateMasterKategoriSkRequest;
use App\Models\Simpeg\MasterKategoriSk;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterKategoriSkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.sk.read') && 
            !$user->hasPermission('simpeg.sk.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master kategori SK.'
        );

        $query = MasterKategoriSk::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['created_at', 'updated_at', 'id', 'nama', 'kode', 'urutan'];
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

    public function store(StoreMasterKategoriSkRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }
        if (!isset($validated['urutan'])) {
            $validated['urutan'] = (MasterKategoriSk::max('urutan') ?? 0) + 1;
        }

        $data = MasterKategoriSk::create($validated);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_master_kategori_sk',
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
            'message' => 'Master kategori SK berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.sk.read') && 
            !$user->hasPermission('simpeg.sk.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master kategori SK.'
        );

        $data = MasterKategoriSk::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data,
        ]);
    }

    public function update(UpdateMasterKategoriSkRequest $request, int $id): JsonResponse
    {
        $data = MasterKategoriSk::findOrFail($id);
        $oldValues = $data->getOriginal();
        $data->update($request->validated());

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_master_kategori_sk',
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
            'message' => 'Master kategori SK berhasil diperbarui',
            'data' => $data,
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.sk.manage') && !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk menghapus master kategori SK.'
        );

        $data = MasterKategoriSk::withCount('skPegawai')->findOrFail($id);

        if ($data->sk_pegawai_count > 0) {
            throw ValidationException::withMessages([
                'id' => ['Tidak dapat menghapus kategori SK karena masih digunakan pada data arsip SK pegawai.'],
            ]);
        }

        $oldValues = $data->getOriginal();
        $data->delete();

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_master_kategori_sk',
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
            'message' => 'Master kategori SK berhasil dihapus',
            'data' => [
                'id' => $id,
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
