<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreTandaTanganRequest;
use App\Http\Requests\Simpeg\UpdateTandaTanganRequest;
use App\Models\Simpeg\TandaTanganPegawai;
use App\Services\Simpeg\TandaTanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TandaTanganController extends Controller
{
    public function __construct(
        protected TandaTanganService $service
    ) {}

    /**
     * Menampilkan daftar tanda tangan digital.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master tanda tangan digital.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'user_id', 'pegawai_id', 'is_active', 'sort_by', 'sort_order']);
        $paginated = $this->service->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tanda tangan digital berhasil diambil',
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
                'user_id' => $request->user_id,
                'pegawai_id' => $request->pegawai_id,
                'is_active' => $request->is_active,
                'sort_by' => $filters['sort_by'] ?? 'created_at',
                'sort_order' => $filters['sort_order'] ?? 'desc',
            ],
        ]);
    }

    /**
     * Menyimpan tanda tangan digital baru.
     */
    public function store(StoreTandaTanganRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan tanda tangan digital.',
            ], 403);
        }

        $tandaTangan = $this->service->store(
            $request->validated(),
            $request->file('file_tanda_tangan')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tanda tangan digital berhasil ditambahkan',
            'data' => $tandaTangan,
        ], 201);
    }

    /**
     * Menampilkan detail spesifik satu tanda tangan.
     */
    public function show(Request $request, TandaTanganPegawai $tandaTangan): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.read') && !$user->isAdmin() && $user->id !== $tandaTangan->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat tanda tangan ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tanda tangan digital berhasil diambil',
            'data' => $tandaTangan->load(['user', 'pegawai']),
        ]);
    }

    /**
     * Memperbarui informasi tanda tangan digital.
     */
    public function update(UpdateTandaTanganRequest $request, TandaTanganPegawai $tandaTangan): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah tanda tangan digital.',
            ], 403);
        }

        $updated = $this->service->update(
            $tandaTangan,
            $request->validated(),
            $request->file('file_tanda_tangan')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tanda tangan digital berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Mengubah status aktif tanda tangan digital.
     */
    public function toggleActive(Request $request, TandaTanganPegawai $tandaTangan): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah status tanda tangan digital.',
            ], 403);
        }

        $updated = $this->service->toggleActive($tandaTangan);

        return response()->json([
            'status' => 'success',
            'message' => 'Status tanda tangan digital berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Menghapus tanda tangan digital.
     */
    public function destroy(Request $request, TandaTanganPegawai $tandaTangan): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.tanda_tangan.delete') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus tanda tangan digital.',
            ], 403);
        }

        $this->service->destroy($tandaTangan);

        return response()->json([
            'status' => 'success',
            'message' => 'Tanda tangan digital berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * Mengambil tanda tangan aktif untuk user tertentu.
     */
    public function getActiveByUser(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('simpeg.tanda_tangan.read') && !$user->isAdmin() && $user->id !== $userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat tanda tangan digital pengguna ini.',
            ], 403);
        }

        $activeSignature = $this->service->getActiveByUser($userId);

        if (!$activeSignature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanda tangan digital aktif tidak ditemukan untuk pengguna ini',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tanda tangan digital aktif berhasil diambil',
            'data' => $activeSignature,
        ]);
    }
}
