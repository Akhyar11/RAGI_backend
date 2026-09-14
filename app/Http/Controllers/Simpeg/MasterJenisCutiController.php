<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterJenisCutiRequest;
use App\Http\Requests\Simpeg\UpdateMasterJenisCutiRequest;
use App\Models\Simpeg\MasterJenisCuti;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterJenisCutiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.read') && 
            !$user->hasPermission('simpeg.cuti.request') && 
            !$user->hasPermission('simpeg.cuti.manage') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master jenis cuti.',
            ], 403);
        }

        $query = MasterJenisCuti::query();

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Filter Tipe Durasi
        if ($request->filled('tipe_durasi')) {
            $query->where('tipe_durasi', $request->tipe_durasi);
        }

        // Filter Status Aktif
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $allowedSort = ['id', 'nama', 'kode', 'tipe_durasi', 'durasi_hari', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama';
        $sortOrder = strtolower($request->sort_order ?? $request->sort_dir ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // All records for dropdown option or paginated
        if ($request->boolean('all')) {
            $data = $query->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Data retrieved successfully',
                'data' => $data,
            ]);
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
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

    public function store(StoreMasterJenisCutiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.manage') && !$user->hasPermission('simpeg.cuti.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan master jenis cuti.',
            ], 403);
        }

        $validated = $request->validated();
        if ($validated['tipe_durasi'] === 'fleksibel') {
            $validated['durasi_hari'] = 0;
        }

        $master = MasterJenisCuti::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis cuti berhasil ditambahkan',
            'data' => $master,
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.read') && 
            !$user->hasPermission('simpeg.cuti.manage') && 
            !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian jenis cuti.',
            ], 403);
        }

        $master = MasterJenisCuti::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail master jenis cuti berhasil dimuat',
            'data' => $master,
        ]);
    }

    public function update(UpdateMasterJenisCutiRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.manage') && !$user->hasPermission('simpeg.cuti.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah master jenis cuti.',
            ], 403);
        }

        $master = MasterJenisCuti::findOrFail($id);
        $validated = $request->validated();

        if (isset($validated['tipe_durasi']) && $validated['tipe_durasi'] === 'fleksibel') {
            $validated['durasi_hari'] = 0;
        }

        $master->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis cuti berhasil diperbarui',
            'data' => $master,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.cuti.manage') && !$user->hasPermission('simpeg.cuti.delete') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus master jenis cuti.',
            ], 403);
        }

        $master = MasterJenisCuti::findOrFail($id);

        // Check if there are active pengajuan cuti records
        $inUse = $master->pengajuanCuti()->exists();
        if ($inUse) {
            // Soft delete
            $master->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Master jenis cuti dinonaktifkan (soft deleted) karena telah memiliki riwayat permohonan.',
            ]);
        }

        $master->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis cuti berhasil dihapus.',
        ]);
    }
}
