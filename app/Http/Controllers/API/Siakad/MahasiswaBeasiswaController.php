<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\StoreMahasiswaBeasiswaRequest;
use App\Http\Requests\Siakad\UpdateMahasiswaBeasiswaRequest;
use App\Services\Siakad\MahasiswaBeasiswaService;
use App\Models\Sikeu\MahasiswaBeasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MahasiswaBeasiswaController extends Controller
{
    public function __construct(
        protected MahasiswaBeasiswaService $service
    ) {}

    /**
     * Tampilkan daftar mahasiswa penerima beasiswa (dengan filter, sorting, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $filters = [
            'search' => $request->input('search') ?: $request->input('q'),
            'status' => $request->input('status'),
            'beasiswa_id' => $request->input('beasiswa_id'),
            'sort_by' => $request->input('sort_by', 'nama_mahasiswa'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        $paginator = $this->service->getPaginatedList($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar penerima beasiswa berhasil dimuat',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Dapatkan daftar program beasiswa aktif (referensi dari SIKEU untuk dropdown BAAK).
     */
    public function getBeasiswaOptions(): JsonResponse
    {
        $options = $this->service->getBeasiswaOptions();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pilihan program beasiswa berhasil dimuat',
            'data' => $options,
        ]);
    }

    /**
     * Tetapkan mahasiswa penerima beasiswa baru (oleh BAAK).
     */
    public function store(StoreMahasiswaBeasiswaRequest $request): JsonResponse
    {
        try {
            $item = $this->service->assignBeasiswa($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Mahasiswa berhasil ditetapkan sebagai penerima beasiswa',
                'data' => $item,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menetapkan penerima beasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tampilkan rincian penetapan beasiswa mahasiswa.
     */
    public function show(int $id): JsonResponse
    {
        $item = MahasiswaBeasiswa::with(['beasiswa', 'mahasiswa.programStudi'])->find($id);

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data penerima beasiswa tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail penerima beasiswa berhasil dimuat',
            'data' => $item,
        ]);
    }

    /**
     * Perbarui penetapan beasiswa mahasiswa.
     */
    public function update(UpdateMahasiswaBeasiswaRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->service->updateBeasiswa($id, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Penetapan beasiswa mahasiswa berhasil diperbarui',
                'data' => $item,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui data penetapan beasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus penetapan beasiswa mahasiswa.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteBeasiswa($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Penetapan beasiswa mahasiswa berhasil dihapus',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data penetapan beasiswa tidak ditemukan',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus penetapan beasiswa: ' . $e->getMessage(),
            ], 500);
        }
    }
}
