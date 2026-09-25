<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\ApproveSuratTugasRequest;
use App\Http\Requests\Simpeg\StoreSuratTugasRequest;
use App\Http\Requests\Simpeg\UpdateSuratTugasRequest;
use App\Http\Requests\Simpeg\UploadLpjRequest;
use App\Models\Simpeg\SuratTugas;
use App\Services\Simpeg\SuratTugasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuratTugasController extends Controller
{
    public function __construct(
        protected SuratTugasService $service
    ) {}

    /**
     * Dapatkan master referensi jenis transportasi & kategori kegiatan
     */
    public function masters(): JsonResponse
    {
        $masters = $this->service->getMasters();

        return response()->json([
            'status' => 'success',
            'message' => 'Master data surat tugas dinas berhasil diambil',
            'data' => $masters,
        ]);
    }

    /**
     * Menampilkan daftar surat tugas dengan filter dan paginasi
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat daftar surat tugas.',
            ], 403);
        }

        $paginated = $this->service->list($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar surat tugas berhasil diambil',
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
     * Menampilkan detail surat tugas, anggota tim rombongan, dan berkas
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian surat tugas.',
            ], 403);
        }

        $suratTugas = $this->service->getById($id, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail surat tugas berhasil diambil',
            'data' => $suratTugas,
        ]);
    }

    /**
     * Membuat permohonan surat tugas dinas baru
     */
    public function store(StoreSuratTugasRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengajukan surat tugas.',
            ], 403);
        }

        $validated = $request->validated();
        $fileSuratTugas = $request->file('file_surat_tugas');
        $fileLpj = $request->file('file_lpj');
        unset($validated['file_surat_tugas'], $validated['file_lpj']);

        $suratTugas = $this->service->create($validated, $fileSuratTugas, $fileLpj, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan surat tugas berhasil dibuat',
            'data' => $suratTugas,
        ], 201);
    }

    /**
     * Memperbarui draf / data surat tugas
     */
    public function update(UpdateSuratTugasRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah data surat tugas.',
            ], 403);
        }

        $suratTugas = SuratTugas::findOrFail($id);
        $validated = $request->validated();
        $fileSuratTugas = $request->file('file_surat_tugas');
        $fileLpj = $request->file('file_lpj');
        unset($validated['file_surat_tugas'], $validated['file_lpj']);

        $updated = $this->service->update($suratTugas, $validated, $fileSuratTugas, $fileLpj, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data surat tugas berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    /**
     * Menyetujui atau menolak permohonan surat tugas (Approver/Admin)
     */
    public function approve(ApproveSuratTugasRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.approve') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menyetujui surat tugas.',
            ], 403);
        }

        $suratTugas = SuratTugas::findOrFail($id);
        $validated = $request->validated();
        $fileSuratTugas = $request->file('file_surat_tugas');
        unset($validated['file_surat_tugas']);

        $approved = $this->service->approve($suratTugas, $validated, $fileSuratTugas, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Status surat tugas dinas berhasil diperbarui',
            'data' => $approved,
        ]);
    }

    /**
     * Mengunggah berkas Laporan Pertanggungjawaban (LPJ) & realisasi biaya
     */
    public function uploadLpj(UploadLpjRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengunggah LPJ.',
            ], 403);
        }

        $suratTugas = SuratTugas::findOrFail($id);
        $validated = $request->validated();
        $fileLpj = $request->file('file_lpj');
        unset($validated['file_lpj']);

        $result = $this->service->uploadLpj($suratTugas, $validated, $fileLpj, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan LPJ dinas berhasil diunggah',
            'data' => $result,
        ]);
    }

    /**
     * Konfirmasi penerimaan panjar oleh dosen pemohon (Tahap 4)
     */
    public function konfirmasiPanjar(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $suratTugas = SuratTugas::findOrFail($id);

        $result = $this->service->konfirmasiPanjar($suratTugas, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Konfirmasi panjar berhasil disimpan. Pengajuan siap dicairkan oleh Keuangan.',
            'data' => $result,
        ]);
    }

    /**
     * Menghapus draf / pengajuan surat tugas
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.surat_tugas.delete') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus surat tugas.',
            ], 403);
        }

        $suratTugas = SuratTugas::findOrFail($id);
        $this->service->delete($suratTugas, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan surat tugas dinas berhasil dihapus',
        ]);
    }
}
