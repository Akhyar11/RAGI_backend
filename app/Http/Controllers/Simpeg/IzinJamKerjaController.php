<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\ApproveIzinJamKerjaRequest;
use App\Http\Requests\Simpeg\StoreIzinJamKerjaRequest;
use App\Services\Simpeg\IzinJamKerjaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IzinJamKerjaController extends Controller
{
    public function __construct(
        protected IzinJamKerjaService $service
    ) {}

    /**
     * Dapatkan master referensi jenis izin jam kerja
     */
    public function masters(): JsonResponse
    {
        $masters = $this->service->getMasters();

        return response()->json([
            'status' => 'success',
            'message' => 'Master jenis izin jam kerja berhasil diambil',
            'data' => $masters,
        ]);
    }

    /**
     * Menampilkan daftar pengajuan izin jam kerja
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat daftar izin jam kerja.',
            ], 403);
        }

        $paginated = $this->service->list($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan izin jam kerja berhasil diambil',
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
     * Menampilkan detail izin jam kerja
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian izin jam kerja.',
            ], 403);
        }

        $izin = $this->service->getById($id, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pengajuan izin jam kerja berhasil diambil',
            'data' => $izin,
        ]);
    }

    /**
     * Membuat pengajuan izin jam kerja baru
     */
    public function store(StoreIzinJamKerjaRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengajukan izin jam kerja.',
            ], 403);
        }

        $validated = $request->validated();
        $fileBukti = $request->file('file_bukti');
        unset($validated['file_bukti']);

        $izin = $this->service->create($validated, $fileBukti, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan izin jam kerja berhasil dibuat',
            'data' => $izin,
        ], 201);
    }

    /**
     * Memperbarui pengajuan izin jam kerja
     */
    public function update(StoreIzinJamKerjaRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah pengajuan izin jam kerja.',
            ], 403);
        }

        $validated = $request->validated();
        $fileBukti = $request->file('file_bukti');
        unset($validated['file_bukti']);

        $updated = $this->service->update($id, $validated, $fileBukti, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan izin jam kerja berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Menyetujui atau menolak permohonan izin jam kerja
     */
    public function approve(ApproveIzinJamKerjaRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.approve') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memproses approval izin jam kerja.',
            ], 403);
        }

        $approved = $this->service->approve($id, $request->validated(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan izin jam kerja berhasil diproses',
            'data' => $approved,
        ]);
    }

    /**
     * Menghapus pengajuan izin jam kerja
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.izin_kerja.delete') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus izin jam kerja.',
            ], 403);
        }

        $this->service->delete($id, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan izin jam kerja berhasil dihapus',
        ]);
    }
}
