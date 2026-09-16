<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreRiwayatPelatihanRequest;
use App\Http\Requests\Simpeg\StoreRiwayatTesRequest;
use App\Http\Requests\Simpeg\StoreSertifikasiDosenRequest;
use App\Http\Requests\Simpeg\UpdateRiwayatPelatihanRequest;
use App\Http\Requests\Simpeg\UpdateRiwayatTesRequest;
use App\Http\Requests\Simpeg\UpdateSertifikasiDosenRequest;
use App\Services\Simpeg\KompetensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KompetensiController extends Controller
{
    public function __construct(
        protected KompetensiService $service
    ) {}

    /**
     * Dapatkan master referensi kompetensi
     */
    public function masters(): JsonResponse
    {
        $masters = $this->service->getMasters();

        return response()->json([
            'status' => 'success',
            'message' => 'Master data kompetensi berhasil diambil',
            'data' => $masters,
        ]);
    }

    // =========================================================================
    // 1. SERTIFIKASI DOSEN
    // =========================================================================

    public function listSertifikasi(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.read') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data sertifikasi dosen.',
            ], 403);
        }

        $paginated = $this->service->listSertifikasi($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data sertifikasi dosen berhasil diambil',
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

    public function storeSertifikasi(StoreSertifikasiDosenRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.create') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan sertifikasi dosen.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $sertifikasi = $this->service->createSertifikasi($validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data sertifikasi dosen berhasil ditambahkan',
            'data' => $sertifikasi->load(['pegawai', 'jenisSertifikasi']),
        ], 201);
    }

    public function updateSertifikasi(UpdateSertifikasiDosenRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.update') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah data sertifikasi dosen.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $sertifikasi = $this->service->updateSertifikasi($id, $validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data sertifikasi dosen berhasil diperbarui',
            'data' => $sertifikasi,
        ]);
    }

    public function destroySertifikasi(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.delete') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus sertifikasi dosen.',
            ], 403);
        }

        $this->service->deleteSertifikasi($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data sertifikasi dosen berhasil dihapus',
        ]);
    }

    // =========================================================================
    // 2. RIWAYAT TES (TOEFL, TKDA, TPA)
    // =========================================================================

    public function listTes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.read') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data riwayat tes kemampuan.',
            ], 403);
        }

        $paginated = $this->service->listTes($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat tes berhasil diambil',
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

    public function storeTes(StoreRiwayatTesRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.create') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan data riwayat tes.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $tes = $this->service->createTes($validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat tes berhasil disimpan',
            'data' => $tes->load(['pegawai', 'jenisTes']),
        ], 201);
    }

    public function updateTes(UpdateRiwayatTesRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.update') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah data riwayat tes.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $tes = $this->service->updateTes($id, $validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat tes berhasil diperbarui',
            'data' => $tes,
        ]);
    }

    public function destroyTes(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.delete') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus data riwayat tes.',
            ], 403);
        }

        $this->service->deleteTes($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat tes berhasil dihapus',
        ]);
    }

    // =========================================================================
    // 3. RIWAYAT PELATIHAN, DIKLAT, WORKSHOP
    // =========================================================================

    public function listPelatihan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.read') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data riwayat pelatihan.',
            ], 403);
        }

        $paginated = $this->service->listPelatihan($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat pelatihan berhasil diambil',
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

    public function storePelatihan(StoreRiwayatPelatihanRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.create') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan data riwayat pelatihan.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $pelatihan = $this->service->createPelatihan($validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat pelatihan berhasil disimpan',
            'data' => $pelatihan->load(['pegawai', 'jenisPelatihan', 'peran', 'tingkat']),
        ], 201);
    }

    public function updatePelatihan(UpdateRiwayatPelatihanRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.update') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah data riwayat pelatihan.',
            ], 403);
        }

        $validated = $request->validated();
        $file = $request->file('file');
        unset($validated['file']);

        $pelatihan = $this->service->updatePelatihan($id, $validated, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat pelatihan berhasil diperbarui',
            'data' => $pelatihan,
        ]);
    }

    public function destroyPelatihan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.delete') && !$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus data riwayat pelatihan.',
            ], 403);
        }

        $this->service->deletePelatihan($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Data riwayat pelatihan berhasil dihapus',
        ]);
    }

    // =========================================================================
    // 4. ADMIN SEARCH & REKAP AKREDITASI
    // =========================================================================

    public function pencarianAdmin(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.kompetensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengelola rekap kompetensi pegawai.',
            ], 403);
        }

        $paginated = $this->service->searchKompetensiAdmin($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Data rekapitulasi kompetensi berhasil diambil',
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
}
