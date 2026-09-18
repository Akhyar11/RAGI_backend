<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterKategoriSkpRequest;
use App\Http\Requests\Simpeg\UpdateMasterKategoriSkpRequest;
use App\Models\Simpeg\MasterKategoriSkp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterKategoriSkpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.read') && !$user->hasPermission('simpeg.kinerja.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master kategori sasaran kinerja.',
            ], 403);
        }

        $query = MasterKategoriSkp::query();

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

        $sortBy = in_array($request->sort_by, ['id', 'nama', 'kode', 'urutan', 'created_at']) ? $request->sort_by : 'urutan';
        $sortDir = strtolower($request->sort_dir ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir)->orderBy('id', 'asc');

        if ($request->boolean('all')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Seluruh data master kategori SKP berhasil diambil',
                'data' => $query->get(),
            ]);
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master kategori SKP berhasil diambil',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    public function store(StoreMasterKategoriSkpRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.create') && !$user->hasPermission('simpeg.kinerja.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambah master kategori sasaran kinerja.',
            ], 403);
        }

        $data = $request->validated();
        if (!isset($data['urutan'])) {
            $data['urutan'] = (MasterKategoriSkp::max('urutan') ?? 0) + 1;
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $kategori = MasterKategoriSkp::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori SKP berhasil ditambahkan',
            'data' => $kategori,
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $kategori = MasterKategoriSkp::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $kategori,
        ]);
    }

    public function update(UpdateMasterKategoriSkpRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.update') && !$user->hasPermission('simpeg.kinerja.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah master kategori sasaran kinerja.',
            ], 403);
        }

        $kategori = MasterKategoriSkp::findOrFail($id);
        $kategori->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori SKP berhasil diperbarui',
            'data' => $kategori,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kinerja.delete') && !$user->hasPermission('simpeg.kinerja.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus master kategori sasaran kinerja.',
            ], 403);
        }

        $kategori = MasterKategoriSkp::findOrFail($id);

        if ($kategori->items()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori SKP ini tidak dapat dihapus karena sudah digunakan dalam butir SKP pegawai.',
            ], 422);
        }

        $kategori->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori SKP berhasil dihapus',
        ]);
    }
}
