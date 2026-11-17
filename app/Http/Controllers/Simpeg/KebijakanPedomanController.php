<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreKebijakanPedomanRequest;
use App\Http\Requests\Simpeg\UpdateKebijakanPedomanRequest;
use App\Services\Simpeg\KebijakanPedomanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KebijakanPedomanController extends Controller
{
    public function __construct(
        protected KebijakanPedomanService $service
    ) {}

    /**
     * Mengambil master data kategori kebijakan aktif
     */
    public function masters(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kebijakan_pedoman.read') && !$user->hasPermission('simpeg.kebijakan_pedoman.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master kategori kebijakan.',
            ], 403);
        }

        $masters = $this->service->getMasters();

        return response()->json([
            'status' => 'success',
            'message' => 'Master kategori kebijakan berhasil diambil',
            'data' => $masters,
        ]);
    }

    /**
     * Menampilkan daftar kebijakan dan pedoman kepegawaian
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kebijakan_pedoman.read') && !$user->hasPermission('simpeg.kebijakan_pedoman.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat kebijakan dan pedoman.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSortColumns = ['created_at', 'tanggal_penetapan', 'tanggal_berlaku', 'judul', 'nomor_surat'];
        $sortBy = in_array($request->input('sort_by'), $allowedSortColumns, true) ? $request->input('sort_by') : 'created_at';

        $filters = [
            'search' => $request->input('search'),
            'kategori_kebijakan_id' => $request->input('kategori_kebijakan_id'),
            'tahun' => $request->input('tahun'),
            'is_active' => $request->input('is_active'),
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $paginated = $this->service->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kebijakan dan pedoman berhasil diambil',
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

    /**
     * Menampilkan detail rincian kebijakan dan pedoman
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kebijakan_pedoman.read') && !$user->hasPermission('simpeg.kebijakan_pedoman.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian kebijakan dan pedoman.',
            ], 403);
        }

        $kebijakan = $this->service->getById($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian kebijakan dan pedoman berhasil diambil',
            'data' => $kebijakan,
        ]);
    }

    /**
     * Menambahkan kebijakan dan pedoman baru beserta unggahan dokumen scan PDF
     */
    public function store(StoreKebijakanPedomanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $file = $request->file('file_dokumen');
        unset($validated['file_dokumen']);

        $kebijakan = $this->service->create($validated, $file, $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kebijakan dan pedoman berhasil ditambahkan dan disinkronkan ke Arsip',
            'data' => $kebijakan,
        ], 201);
    }

    /**
     * Memperbarui informasi atau mengganti berkas scan kebijakan dan pedoman
     */
    public function update(UpdateKebijakanPedomanRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $file = $request->file('file_dokumen');
        unset($validated['file_dokumen']);

        $updated = $this->service->update($id, $validated, $file, $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kebijakan dan pedoman berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Menghapus kebijakan dan pedoman (soft delete)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('simpeg.kebijakan_pedoman.delete') && !$user->hasPermission('simpeg.kebijakan_pedoman.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus kebijakan dan pedoman.',
            ], 403);
        }

        $this->service->delete($id, $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kebijakan dan pedoman berhasil dihapus',
            'data' => null,
        ]);
    }
}
