<?php

namespace App\Http\Controllers\Arsip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Arsip\StoreKopSuratRequest;
use App\Http\Requests\Arsip\UpdateKopSuratRequest;
use App\Models\Arsip\KopSurat;
use App\Services\Arsip\KopSuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KopSuratController extends Controller
{
    public function __construct(
        protected KopSuratService $service
    ) {}

    /**
     * Menampilkan daftar master kop surat.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.kop_surat.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master kop surat.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'versi', 'is_active']);
        $paginated = $this->service->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar master kop surat berhasil dimuat.',
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
                'versi' => $request->versi,
                'is_active' => $request->is_active,
            ],
        ]);
    }

    /**
     * Upload dan simpan master kop surat baru.
     */
    public function store(StoreKopSuratRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menambahkan kop surat.',
            ], 403);
        }

        $kop = $this->service->store($request->validated(), $request->file('file_kop'));

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas kop surat berhasil disimpan.',
            'data' => $kop,
        ], 201);
    }

    /**
     * Menampilkan detail spesifik kop surat.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.kop_surat.read') && !$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat kop surat.',
            ], 403);
        }

        $kop = KopSurat::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kop surat berhasil dimuat.',
            'data' => $kop,
        ]);
    }

    /**
     * Memperbarui data atau mengganti berkas kop surat.
     */
    public function update(UpdateKopSuratRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah kop surat.',
            ], 403);
        }

        $kop = $this->service->update(
            $id,
            $request->validated(),
            $request->file('file_kop')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kop surat berhasil diperbarui.',
            'data' => $kop,
        ]);
    }

    /**
     * Menghapus master kop surat.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus kop surat.',
            ], 403);
        }

        $this->service->destroy($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kop surat berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Toggle status aktif kop surat.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah status kop surat.',
            ], 403);
        }

        $kop = $this->service->toggleActive($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Status aktif kop surat berhasil diubah.',
            'data' => $kop,
        ]);
    }

    /**
     * Mengambil kop surat yang berlaku berdasarkan tahun surat:
     * - Parameter ?year=YYYY (default tahun sekarang)
     * - Jika < 2021: mengembalikan versi lama
     * - Jika >= 2021: mengembalikan versi baru
     */
    public function getByYear(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.kop_surat.read') && !$user->hasPermission('arsip.kop_surat.manage') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat kop surat.',
            ], 403);
        }

        $year = $request->integer('year', (int) date('Y'));
        $kop = $this->service->getKopSuratByTahun($year);

        return response()->json([
            'status' => 'success',
            'message' => "Kop surat untuk tahun {$year} berhasil dimuat.",
            'data' => $kop,
            'tahun_query' => $year,
            'versi_diterapkan' => ($year < 2021) ? 'lama' : 'baru',
        ]);
    }
}
