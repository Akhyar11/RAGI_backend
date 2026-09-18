<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterKategoriKegiatanRequest;
use App\Http\Requests\Simpeg\UpdateMasterKategoriKegiatanRequest;
use App\Models\Simpeg\MasterKategoriKegiatanTugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterKategoriKegiatanTugasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.read') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master kategori kegiatan penugasan.',
            ], 403);
        }

        $query = MasterKategoriKegiatanTugas::query();

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

        $sortBy = in_array($request->sort_by, ['id', 'nama', 'urutan', 'created_at']) ? $request->sort_by : 'urutan';
        $sortDir = strtolower($request->sort_dir ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir)->orderBy('id', 'asc');

        if ($request->boolean('all')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Seluruh data master kategori kegiatan berhasil diambil',
                'data' => $query->get(),
            ]);
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master kategori kegiatan berhasil diambil',
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

    public function store(StoreMasterKategoriKegiatanRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.create') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambah master kategori kegiatan penugasan.',
            ], 403);
        }

        $data = $request->validated();
        if (!isset($data['urutan'])) {
            $data['urutan'] = (MasterKategoriKegiatanTugas::max('urutan') ?? 0) + 1;
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $kategori = MasterKategoriKegiatanTugas::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori kegiatan berhasil ditambahkan',
            'data' => $kategori,
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $kategori = MasterKategoriKegiatanTugas::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $kategori,
        ]);
    }

    public function update(UpdateMasterKategoriKegiatanRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.update') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah master kategori kegiatan penugasan.',
            ], 403);
        }

        $kategori = MasterKategoriKegiatanTugas::findOrFail($id);
        $kategori->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori kegiatan berhasil diperbarui',
            'data' => $kategori,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.delete') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus master kategori kegiatan penugasan.',
            ], 403);
        }

        $kategori = MasterKategoriKegiatanTugas::findOrFail($id);

        if ($kategori->suratTugas()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori kegiatan ini tidak dapat dihapus karena sudah digunakan pada surat tugas dinas.',
            ], 422);
        }

        $kategori->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori kegiatan berhasil dihapus',
        ]);
    }
}
