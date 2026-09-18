<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreMasterJenisTransportasiRequest;
use App\Http\Requests\Simpeg\UpdateMasterJenisTransportasiRequest;
use App\Models\Simpeg\MasterJenisTransportasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterJenisTransportasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.read') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master jenis transportasi dinas.',
            ], 403);
        }

        $query = MasterJenisTransportasi::query();

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
                'message' => 'Seluruh data master jenis transportasi berhasil diambil',
                'data' => $query->get(),
            ]);
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master jenis transportasi berhasil diambil',
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

    public function store(StoreMasterJenisTransportasiRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.create') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambah master jenis transportasi dinas.',
            ], 403);
        }

        $data = $request->validated();
        if (!isset($data['urutan'])) {
            $data['urutan'] = (MasterJenisTransportasi::max('urutan') ?? 0) + 1;
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $transportasi = MasterJenisTransportasi::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Master moda transportasi berhasil ditambahkan',
            'data' => $transportasi,
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $transportasi = MasterJenisTransportasi::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $transportasi,
        ]);
    }

    public function update(UpdateMasterJenisTransportasiRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.update') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah master jenis transportasi dinas.',
            ], 403);
        }

        $transportasi = MasterJenisTransportasi::findOrFail($id);
        $transportasi->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Master moda transportasi berhasil diperbarui',
            'data' => $transportasi,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.delete') && !$user->hasPermission('simpeg.surat_tugas.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus master jenis transportasi dinas.',
            ], 403);
        }

        $transportasi = MasterJenisTransportasi::findOrFail($id);

        if ($transportasi->suratTugas()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Moda transportasi ini tidak dapat dihapus karena sudah digunakan pada surat tugas dinas.',
            ], 422);
        }

        $transportasi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Master moda transportasi berhasil dihapus',
        ]);
    }
}
