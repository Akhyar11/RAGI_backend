<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterJenisIzinJamKerjaRequest;
use App\Http\Requests\Simpeg\UpdateMasterJenisIzinJamKerjaRequest;
use App\Models\Simpeg\MasterJenisIzinJamKerja;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterJenisIzinJamKerjaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.cuti.read') && 
            !$user->hasPermission('simpeg.cuti.request') && 
            !$user->hasPermission('simpeg.cuti.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master jenis izin jam kerja.'
        );

        $query = MasterJenisIzinJamKerja::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipe_potongan')) {
            $query->where('tipe_potongan', $request->tipe_potongan);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['created_at', 'updated_at', 'id', 'nama', 'kode', 'tipe_potongan', 'urutan'];
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

    public function store(StoreMasterJenisIzinJamKerjaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }
        if (!isset($validated['urutan'])) {
            $validated['urutan'] = (MasterJenisIzinJamKerja::max('urutan') ?? 0) + 1;
        }

        $data = MasterJenisIzinJamKerja::create($validated);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_master_jenis_izin_jam_kerja',
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
            'message' => 'Master jenis izin jam kerja berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.cuti.read') && 
            !$user->hasPermission('simpeg.cuti.request') && 
            !$user->hasPermission('simpeg.cuti.manage') && 
            !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk melihat master jenis izin jam kerja.'
        );

        $data = MasterJenisIzinJamKerja::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data,
        ]);
    }

    public function update(UpdateMasterJenisIzinJamKerjaRequest $request, int $id): JsonResponse
    {
        $data = MasterJenisIzinJamKerja::findOrFail($id);
        $oldValues = $data->getOriginal();
        $data->update($request->validated());

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_master_jenis_izin_jam_kerja',
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
            'message' => 'Master jenis izin jam kerja berhasil diperbarui',
            'data' => $data,
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(
            !$user->hasPermission('simpeg.cuti.manage') && !$user->isAdmin(),
            403,
            'Anda tidak memiliki hak akses untuk menghapus master jenis izin jam kerja.'
        );

        $data = MasterJenisIzinJamKerja::withCount('izinJamKerja')->findOrFail($id);

        if ($data->izin_jam_kerja_count > 0) {
            throw ValidationException::withMessages([
                'id' => ['Tidak dapat menghapus jenis izin jam kerja karena masih digunakan pada data permohonan izin pegawai.'],
            ]);
        }

        $oldValues = $data->getOriginal();
        $data->delete();

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_master_jenis_izin_jam_kerja',
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
            'message' => 'Master jenis izin jam kerja berhasil dihapus',
            'data' => [
                'id' => $id,
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
