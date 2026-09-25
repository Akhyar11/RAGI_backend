<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Lms\StoreMateriRequest;
use App\Http\Requests\Siakad\Lms\UpdateMateriRequest;
use App\Http\Requests\Siakad\Lms\UploadMateriFileRequest;
use App\Http\Requests\Siakad\Lms\StoreTugasRequest;
use App\Http\Requests\Siakad\Lms\UpdateTugasRequest;
use App\Http\Requests\Siakad\Lms\BeriNilaiTugasRequest;
use App\Http\Requests\Siakad\Lms\KumpulkanTugasRequest;
use App\Http\Requests\Siakad\Lms\InputTokenAbsensiRequest;
use App\Http\Requests\Siakad\Lms\BulkAbsensiRequest;
use App\Http\Requests\Siakad\Lms\AjukanIzinRequest;
use App\Http\Requests\Siakad\Lms\ProsesIzinRequest;
use App\Http\Requests\Siakad\Lms\UpdateKelasLmsSettingRequest;
use App\Services\Siakad\LmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LmsController extends Controller
{
    public function __construct(
        protected LmsService $lmsService
    ) {}

    /**
     * Dapatkan ringkasan kelas LMS (16 pertemuan, progres materi/absensi).
     */
    public function getOverview(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $overview = $this->lmsService->getKelasOverview($kelasId, (int) $request->user()->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Overview kelas LMS berhasil diambil.',
            'data'    => $overview,
        ]);
    }

    /**
     * Dapatkan detail satu pertemuan perkuliahan LMS.
     */
    public function getPertemuan(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $detail = $this->lmsService->getPertemuanDetail($id, (int) $request->user()->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail pertemuan LMS berhasil diambil.',
            'data'    => $detail,
        ]);
    }

    /**
     * Tambah konten materi pembelajaran baru.
     */
    public function storeMateri(StoreMateriRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $file = $request->file('file');
        $materi = $this->lmsService->storeMateri($pertemuanId, $request->validated(), $file);

        return response()->json([
            'status'  => 'success',
            'message' => 'Materi pembelajaran berhasil ditambahkan.',
            'data'    => $materi,
        ], 201);
    }

    /**
     * Perbarui data materi pembelajaran.
     */
    public function updateMateri(UpdateMateriRequest $request, int $materiId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $materi = $this->lmsService->updateMateri($materiId, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Materi pembelajaran berhasil diperbarui.',
            'data'    => $materi,
        ]);
    }

    /**
     * Hapus materi pembelajaran.
     */
    public function destroyMateri(int $materiId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $this->lmsService->deleteMateri($materiId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Materi pembelajaran berhasil dihapus.',
            'data'    => [
                'id'         => $materiId,
                'is_deleted' => true,
            ],
        ]);
    }

    /**
     * Unggah lampiran berkas materi tambahan.
     */
    public function uploadMateriFile(UploadMateriFileRequest $request, int $materiId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $file = $this->lmsService->uploadMateriFile($materiId, $request->file('file'));

        return response()->json([
            'status'  => 'success',
            'message' => 'Berkas lampiran materi berhasil diunggah.',
            'data'    => $file,
        ], 201);
    }

    /**
     * Hapus lampiran berkas materi.
     */
    public function destroyMateriFile(int $fileId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $this->lmsService->deleteMateriFile($fileId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Lampiran berkas materi berhasil dihapus.',
            'data'    => [
                'id'         => $fileId,
                'is_deleted' => true,
            ],
        ]);
    }

    /**
     * Buat tugas baru per pertemuan.
     */
    public function storeTugas(StoreTugasRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $tugas = $this->lmsService->storeTugas($pertemuanId, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Tugas perkuliahan berhasil dibuat.',
            'data'    => $tugas,
        ], 201);
    }

    /**
     * Perbarui tugas perkuliahan.
     */
    public function updateTugas(UpdateTugasRequest $request, int $tugasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $tugas = $this->lmsService->updateTugas($tugasId, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Tugas perkuliahan berhasil diperbarui.',
            'data'    => $tugas,
        ]);
    }

    /**
     * Hapus tugas perkuliahan.
     */
    public function destroyTugas(int $tugasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $this->lmsService->deleteTugas($tugasId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tugas perkuliahan berhasil dihapus.',
            'data'    => [
                'id'         => $tugasId,
                'is_deleted' => true,
            ],
        ]);
    }

    /**
     * Dosen memberi nilai pengumpulan tugas (auto-sync ke OBE jika di-link).
     */
    public function beriNilaiTugas(BeriNilaiTugasRequest $request, int $pengumpulanId): JsonResponse
    {
        Gate::authorize('siakad.nilai.manage');

        $pengumpulan = $this->lmsService->beriNilaiTugas(
            $pengumpulanId,
            (float) $request->validated('nilai'),
            $request->validated('feedback_dosen'),
            (int) $request->user()->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Nilai tugas berhasil disimpan dan disinkronkan ke sistem OBE.',
            'data'    => $pengumpulan,
        ]);
    }

    /**
     * Generate 6-digit token absensi realtime per pertemuan.
     */
    public function generateToken(int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $tokenData = $this->lmsService->generateTokenAbsensi($pertemuanId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Token absensi berhasil digenerate.',
            'data'    => $tokenData,
        ]);
    }

    /**
     * Input absensi massal oleh dosen.
     */
    public function bulkAbsensi(BulkAbsensiRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $this->lmsService->bulkInputAbsensi($pertemuanId, $request->validated('absensi'));

        return response()->json([
            'status'  => 'success',
            'message' => 'Data absensi mahasiswa berhasil disimpan.',
            'data'    => [
                'pertemuan_id' => $pertemuanId,
                'total_saved'  => count($request->validated('absensi')),
            ],
        ]);
    }

    /**
     * Dosen memproses pengajuan izin/sakit mahasiswa.
     */
    public function prosesIzin(ProsesIzinRequest $request, int $izinId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $izin = $this->lmsService->prosesIzin(
            $izinId,
            (int) $request->validated('status_id'),
            $request->validated('catatan_dosen'),
            (int) $request->user()->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan izin berhasil diproses.',
            'data'    => $izin,
        ]);
    }

    /**
     * Dapatkan rekapitulasi kehadiran seluruh kelas.
     */
    public function getRekapAbsensi(int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $rekap = $this->lmsService->getRekapAbsensiKelas($kelasId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekapitulasi absensi kelas berhasil diambil.',
            'data'    => $rekap,
        ]);
    }

    /**
     * Perbarui konfigurasi LMS kelas.
     */
    public function updateSetting(UpdateKelasLmsSettingRequest $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $setting = $this->lmsService->updateKelasLmsSetting($kelasId, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan LMS kelas berhasil disimpan.',
            'data'    => $setting,
        ]);
    }

    /**
     * Mahasiswa mengumpulkan berkas tugas.
     */
    public function kumpulkanTugas(KumpulkanTugasRequest $request, int $tugasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $file = $request->file('file');
        $pengumpulan = $this->lmsService->kumpulkanTugas(
            $tugasId,
            (int) $request->user()->id,
            $request->validated(),
            $file
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Tugas berhasil dikumpulkan.',
            'data'    => $pengumpulan,
        ]);
    }

    /**
     * Mahasiswa menginputkan 6-digit token absensi.
     */
    public function inputToken(InputTokenAbsensiRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $this->lmsService->inputTokenAbsensi(
            $pertemuanId,
            (int) $request->user()->id,
            $request->validated('token')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Kehadiran Anda berhasil dicatat via token LMS.',
            'data'    => [
                'pertemuan_id' => $pertemuanId,
                'is_present'   => true,
            ],
        ]);
    }

    /**
     * Mahasiswa mengajukan permohonan izin/sakit.
     */
    public function ajukanIzin(AjukanIzinRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $file = $request->file('file_surat');
        $izin = $this->lmsService->ajukanIzin(
            $pertemuanId,
            (int) $request->user()->id,
            $request->validated(),
            $file
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan izin/sakit berhasil dikirimkan ke dosen pengampu.',
            'data'    => $izin,
        ], 201);
    }

    /**
     * Dapatkan daftar kelas yang diikuti pengguna (Dosen/Mahasiswa).
     */
    public function getMyKelas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $perPage      = min(100, $request->integer('per_page', 15));
        $search       = $request->query('search');
        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortBy       = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortOrder    = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $paginator = $this->lmsService->getMyKelas(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kelas LMS berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'     => $search,
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Dapatkan seluruh tugas perkuliahan milik mahasiswa.
     */
    public function getMyAllTugas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $perPage      = min(100, $request->integer('per_page', 15));
        $search       = $request->query('search');
        $allowedSorts = ['id', 'judul', 'deadline_at', 'created_at', 'updated_at'];
        $sortBy       = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortOrder    = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $paginator = $this->lmsService->getMyAllTugas(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar seluruh tugas LMS berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'     => $search,
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Dapatkan URL unduh aman berkas materi atau berkas tugas.
     */
    public function downloadFile(Request $request, string $type, int $id): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $download = $this->lmsService->getDownloadUrl($type, $id, (int) $request->user()->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tautan unduh berkas berhasil disiapkan.',
            'data'    => $download,
        ]);
    }
}
