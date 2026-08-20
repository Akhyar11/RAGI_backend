<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\TipeUjianSpmb;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Services\MenuService;

class TipeUjianController extends Controller
{
    /**
     * Get all Tipe Ujian Master
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!MenuService::hasAccess($user, '/spmb/master/tipe-ujian')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Menu ini belum diaktifkan untuk role Anda di database.'
            ], 403);
        }

        $query = TipeUjianSpmb::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('id', 'asc');

        if ($request->has('page')) {
            $perPage = (int) ($request->per_page ?? 15);
            $data = $query->paginate($perPage);
        } else {
            $data = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Show single Tipe Ujian
     */
    public function show($id): JsonResponse
    {
        $item = TipeUjianSpmb::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $item,
        ]);
    }

    /**
     * Store new Tipe Ujian
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:tipe_ujian_spmb,kode',
            'nama' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $item = TipeUjianSpmb::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Master tipe ujian berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * Update Tipe Ujian
     */
    public function update(Request $request, $id): JsonResponse
    {
        $item = TipeUjianSpmb::findOrFail($id);

        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:tipe_ujian_spmb,kode,' . $id,
            'nama' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Master tipe ujian berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    /**
     * Delete Tipe Ujian
     */
    public function destroy($id): JsonResponse
    {
        $item = TipeUjianSpmb::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Master tipe ujian berhasil dihapus.',
        ]);
    }
}
