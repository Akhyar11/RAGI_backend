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
use App\Http\Requests\Siakad\Lms\UpdateKumpulkanTugasRequest;
use App\Http\Requests\Siakad\Lms\InputTokenAbsensiRequest;
use App\Http\Requests\Siakad\Lms\BulkAbsensiRequest;
use App\Http\Requests\Siakad\Lms\GenerateTokenRequest;
use App\Http\Requests\Siakad\Lms\AjukanIzinRequest;
use App\Http\Requests\Siakad\Lms\ProsesIzinRequest;
use App\Http\Requests\Siakad\Lms\UpdateKelasLmsSettingRequest;
use App\Http\Requests\Siakad\Lms\UpdatePertemuanRequest;
use App\Http\Requests\Siakad\Lms\UpdatePengaturanLmsRequest;
use App\Http\Requests\Siakad\Lms\ExportLmsRequest;
use App\Models\Lms\KelasLmsSetting;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Pertemuan;
use App\Services\AuditLogService;
use App\Services\Siakad\LmsExportService;
use App\Services\Siakad\LmsModuleSettingService;
use App\Services\Siakad\LmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsController extends Controller
{
    public function __construct(
        protected LmsService $lmsService,
        protected LmsExportService $lmsExportService,
        protected LmsModuleSettingService $lmsModuleSettingService
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
     * Daftar pertemuan dari seluruh kelas yang bisa diakses user (agregat module-level).
     *
     * Endpoint ini berada di level modul (tanpa kelasId) untuk mendukung halaman
     * /lms/pertemuan. Endpoint per-kelas tetap dipakai di dalam konteks satu kelas.
     */
    public function listPertemuanSaya(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $allowedSorts = ['id', 'tanggal', 'pertemuan_ke', 'jam_mulai', 'status_pertemuan', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'tanggal';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        $statusPertemuan = $request->filled('status_pertemuan')
            ? (string) $request->input('status_pertemuan')
            : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;

        $paginator = $this->lmsService->listPertemuanSaya(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $statusPertemuan,
            $kelasId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pertemuan LMS berhasil diambil.',
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
                'search'            => $search,
                'sort_by'           => $sortBy,
                'sort_order'        => $sortOrder,
                'tahun_akademik_id' => $tahunAkademikId,
                'status_pertemuan'  => $statusPertemuan,
                'kelas_id'          => $kelasId,
            ],
        ]);
    }

    /**
     * Rekap pengaturan LMS per kelas yang bisa diakses user (agregat module-level).
     */
    public function indexPengaturan(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'nama_kelas';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        // Nilai di luar closed-set resmi diabaikan (fallback diam), sama seperti
        // perlakuan pada sort_by, agar filter tidak pernah menghasilkan 500.
        $metodeAbsensi = in_array($request->input('metode_absensi'), KelasLmsSetting::METODE_ABSENSI, true)
            ? (string) $request->input('metode_absensi')
            : null;
        $statusKonfigurasi = in_array($request->input('status_konfigurasi'), LmsService::STATUS_PENGATURAN, true)
            ? (string) $request->input('status_konfigurasi')
            : null;

        $paginator = $this->lmsService->indexPengaturan(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $metodeAbsensi,
            $statusKonfigurasi
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekap pengaturan LMS berhasil diambil.',
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
                'search'            => $search,
                'sort_by'           => $sortBy,
                'sort_order'        => $sortOrder,
                'tahun_akademik_id' => $tahunAkademikId,
                'metode_absensi'    => $metodeAbsensi,
                'status_konfigurasi' => $statusKonfigurasi,
            ],
        ]);
    }

    /**
     * Ubah data pertemuan (nomor, tanggal, materi, jam, status).
     */
    public function updatePertemuan(UpdatePertemuanRequest $request, int $pertemuanId): JsonResponse
    {
        // Otorisasi ditegakkan eksplisit di controller agar konsisten dengan
        // aksi kelola pertemuan lainnya (token, presensi, hapus).
        Gate::authorize('siakad.kelas.manage');

        $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
        $oldValues = $pertemuan->getOriginal();

        $detail = $this->lmsService->updatePertemuan($pertemuan, $request->validated());

        // Hanya atribut yang benar-benar berubah yang dicatat (dirty tracking Eloquent).
        $newValues = $detail->getChanges();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuanId,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log update pertemuan: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Pertemuan berhasil diperbarui.',
            'data'    => $detail,
        ]);
    }

    /**
     * Hapus pertemuan. Diblokir 422 bila sudah punya data turunan.
     */
    public function destroyPertemuan(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        $oldValues = $pertemuan->getOriginal();

        // Dicatat SEBELUM penghapusan agar jejak audit memuat data asli yang lengkap.
        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuanId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus pertemuan: ' . $e->getMessage());
        }

        $this->lmsService->destroyPertemuan($pertemuan);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pertemuan berhasil dihapus.',
            'data'    => null,
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
    public function generateToken(GenerateTokenRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');
        $this->lmsService->assertPresensiFiturAktif($request->user());

        // Nilai lama diambil sebelum token digenerate supaya audit trail membandingkan
        // state sebelum dan sesudah perubahan.
        $oldValues = Pertemuan::findOrFail($pertemuanId)->getOriginal();

        $tokenData = $this->lmsService->generateTokenAbsensi($pertemuanId, $request->validated('window_menit'));

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuanId,
                oldValues: $oldValues,
                newValues: ['token' => $tokenData['token'] ?? null, 'window_menit' => $request->validated('window_menit')]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log generate token: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Token absensi berhasil digenerate.',
            'data'    => $tokenData,
        ]);
    }

    /**
     * Putar ulang token absensi dengan umur pendek (anti titip-hadir).
     */
    public function rotateToken(int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');
        $this->lmsService->assertPresensiFiturAktif(request()->user());

        $existing = \App\Models\Siakad\Pertemuan::find($pertemuanId);
        $oldValues = $existing ? $existing->getOriginal() : null;

        $tokenData = $this->lmsService->rotateTokenAbsensi($pertemuanId);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuanId,
                oldValues: $oldValues,
                newValues: ['token' => $tokenData['token'] ?? null]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log putar token: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Token absensi berhasil diputar ulang.',
            'data'    => $tokenData,
        ]);
    }

    /**
     * Dosen menutup sesi presensi pertemuan.
     */
    public function tutupPresensi(int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');
        $this->lmsService->assertPresensiFiturAktif(request()->user());

        $existing = \App\Models\Siakad\Pertemuan::findOrFail($pertemuanId);
        $oldValues = $existing->getOriginal();

        $pertemuan = $this->lmsService->tutupPresensi($pertemuanId);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuanId,
                oldValues: $oldValues,
                newValues: $pertemuan->getChanges() ?: ['status_pertemuan' => $pertemuan->status_pertemuan, 'presensi_closed_at' => $pertemuan->presensi_closed_at]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tutup presensi: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesi presensi berhasil ditutup.',
            'data'    => $pertemuan,
        ]);
    }

    /**
     * Input absensi massal oleh dosen.
     */
    public function bulkAbsensi(BulkAbsensiRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');
        $this->lmsService->assertPresensiFiturAktif($request->user());

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
     * Dapatkan rekapitulasi kehadiran kelas.
     * Pemanggil mahasiswa hanya menerima baris miliknya sendiri (privasi data).
     */
    public function getRekapAbsensi(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->lmsService->assertPresensiFiturAktif($request->user());

        $user = $request->user();
        $onlyMahasiswaId = null;
        if ($user && $user->hasRole('mahasiswa') && !$user->can('siakad.kelas.manage')) {
            $onlyMahasiswaId = Mahasiswa::where('user_id', $user->id)->value('id');
        }

        $rekap = $this->lmsService->getRekapAbsensiKelas($kelasId, $onlyMahasiswaId);

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
     * Mahasiswa memperbarui pengumpulan tugasnya (berkas &/atau catatan).
     *
     * Hanya bisa dilakukan bila pengumpulan belum dinilai.
     */
    public function updatePengumpulan(UpdateKumpulkanTugasRequest $request, int $tugasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $file = $request->file('file');
        $pengumpulan = $this->lmsService->updatePengumpulan(
            $tugasId,
            (int) $request->user()->id,
            $request->validated(),
            $file
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengumpulan tugas berhasil diperbarui.',
            'data'    => $pengumpulan,
        ]);
    }

    /**
     * Mahasiswa menarik kembali / menghapus pengumpulan tugasnya.
     */
    public function hapusPengumpulan(Request $request, int $tugasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $this->lmsService->hapusPengumpulan($tugasId, (int) $request->user()->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengumpulan tugas berhasil dihapus.',
            'data'    => null,
        ]);
    }

    /**
     * Ambil pengaturan LMS satu kelas (read-only).
     */
    public function getSetting(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $setting = $this->lmsService->getKelasSetting($kelasId, (int) $request->user()->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan LMS kelas berhasil diambil.',
            'data'    => $setting,
        ]);
    }

    /**
     * Mahasiswa menginputkan 6-digit token absensi.
     */
    public function inputToken(InputTokenAbsensiRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->lmsService->assertPresensiFiturAktif($request->user());

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
        $search       = $request->filled('search') ? (string) $request->input('search') : null;
        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortBy       = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder    = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;

        $paginator = $this->lmsService->getMyKelas(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId
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
                'search'            => $search,
                'sort_by'           => $sortBy,
                'sort_order'        => $sortOrder,
                'tahun_akademik_id' => $tahunAkademikId,
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
        $search       = $request->filled('search') ? (string) $request->input('search') : null;
        $allowedSorts = ['id', 'judul', 'deadline_at', 'created_at', 'updated_at'];
        $sortBy       = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder    = $request->sort_order === 'asc' ? 'asc' : 'desc';

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

    /**
     * Salin materi dari kelas sumber ke kelas target (pertemuan_ke sama).
     */
    public function importMateri(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $validated = $request->validate([
            'sumber_kelas_id' => 'required|integer|exists:siakad_kelas,id',
            'copy_materi'     => 'sometimes|boolean',
            'copy_deskripsi'  => 'sometimes|boolean',
        ]);

        $hasil = $this->lmsService->importMateriDariKelas(
            $kelasId,
            (int) $validated['sumber_kelas_id'],
            [
                'copy_materi'    => $validated['copy_materi'] ?? true,
                'copy_deskripsi' => $validated['copy_deskripsi'] ?? true,
            ]
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_materi_pertemuan',
                recordId: $kelasId,
                oldValues: null,
                newValues: [
                    'sumber_kelas_id' => $validated['sumber_kelas_id'],
                    'disalin_materi'  => $hasil['disalin_materi'],
                    'dibuat_pertemuan' => $hasil['dibuat_pertemuan'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log import materi: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Materi kelas sumber berhasil disalin ke kelas target.',
            'data'    => $hasil,
        ]);
    }

    /**
     * Rekap matriks kehadiran (rekap + peta status per pertemuan).
     * Pemanggil mahasiswa hanya menerima baris miliknya sendiri (privasi data).
     */
    public function rekapMatrix(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        $onlyMahasiswaId = null;
        if ($user && $user->hasRole('mahasiswa') && !$user->can('siakad.kelas.manage')) {
            $onlyMahasiswaId = Mahasiswa::where('user_id', $user->id)->value('id');
        }

        $matriks = $this->lmsService->getRekapMatrix($kelasId, $onlyMahasiswaId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Matriks rekapitulasi absensi kelas berhasil diambil.',
            'data'    => $matriks,
        ]);
    }

    /**
     * Ketercapaian mata kuliah per komponen penilaian OBE.
     * Mahasiswa hanya melihat miliknya sendiri; dosen/pengelola melihat semua.
     */
    public function ketercapaian(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        $mahasiswaId = null;
        if ($user && $user->hasRole('mahasiswa') && !$user->can('siakad.kelas.manage')) {
            $mahasiswaId = Mahasiswa::where('user_id', $user->id)->value('id');
        }

        $capaian = $this->lmsService->getKetercapaianMk($kelasId, $mahasiswaId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Ketercapaian mata kuliah berhasil diambil.',
            'data'    => $capaian,
        ]);
    }

    /* ---------------------------------------------------------------------
     |  Export Laporan
     | ------------------------------------------------------------------ */

    /**
     * Export rekap nilai (tugas + quiz) kelas dalam format CSV/XLSX.
     */
    public function exportNilai(ExportLmsRequest $request, int $kelasId): StreamedResponse
    {
        Gate::authorize('siakad.nilai.manage');

        $userId = (int) $request->user()->id;
        $this->lmsExportService->assertKelasAccess($kelasId, $userId);

        $data = $this->lmsExportService->rekapNilaiKelas($kelasId);
        $format = $request->exportFormat();

        $this->catatExport($request, 'siakad_kelas', $kelasId, 'rekap_nilai', $format, count($data['rows']));

        return $this->lmsExportService->download($data['filename'], $data['headers'], $data['rows'], $format);
    }

    /**
     * Export rekap absensi kelas dalam format CSV/XLSX.
     */
    public function exportPresensi(ExportLmsRequest $request, int $kelasId): StreamedResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $userId = (int) $request->user()->id;
        $this->lmsExportService->assertKelasAccess($kelasId, $userId);

        $data = $this->lmsExportService->rekapPresensiKelas($kelasId);
        $format = $request->exportFormat();

        $this->catatExport($request, 'siakad_kelas', $kelasId, 'rekap_absensi', $format, count($data['rows']));

        return $this->lmsExportService->download($data['filename'], $data['headers'], $data['rows'], $format);
    }

    /**
     * Export rekap pengajuan izin absensi kelas dalam format CSV/XLSX.
     */
    public function exportIzin(ExportLmsRequest $request, int $kelasId): StreamedResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $userId = (int) $request->user()->id;
        $this->lmsExportService->assertKelasAccess($kelasId, $userId);

        $data = $this->lmsExportService->rekapIzinKelas($kelasId);
        $format = $request->exportFormat();

        $this->catatExport($request, 'siakad_kelas', $kelasId, 'rekap_izin_absensi', $format, count($data['rows']));

        return $this->lmsExportService->download($data['filename'], $data['headers'], $data['rows'], $format);
    }

    /**
     * Export status & nilai pengumpulan tugas dalam format CSV/XLSX.
     */
    public function exportPengumpulan(ExportLmsRequest $request, int $tugasId): StreamedResponse
    {
        Gate::authorize('siakad.nilai.manage');

        $userId = (int) $request->user()->id;
        $this->lmsExportService->assertTugasAccess($tugasId, $userId);

        $data = $this->lmsExportService->pengumpulanTugas($tugasId);
        $format = $request->exportFormat();

        $this->catatExport($request, 'lms_tugas', $tugasId, 'pengumpulan_tugas', $format, count($data['rows']));

        return $this->lmsExportService->download($data['filename'], $data['headers'], $data['rows'], $format);
    }

    /**
     * Catat jejak audit untuk setiap aksi export laporan LMS.
     */
    protected function catatExport(
        ExportLmsRequest $request,
        string $tableName,
        int $recordId,
        string $jenisLaporan,
        string $format,
        int $jumlahBaris
    ): void {
        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'export',
                tableName: $tableName,
                recordId: $recordId,
                newValues: [
                    'jenis_laporan' => $jenisLaporan,
                    'format'        => $format,
                    'jumlah_baris'  => $jumlahBaris,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log export LMS: ' . $e->getMessage());
        }
    }

    /**
     * Ringkasan dashboard LMS (statistik split role + tren 7 hari, cache 5 menit).
     */
    public function dashboardOverview(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        return response()->json([
            'status'  => 'success',
            'message' => 'Ringkasan dashboard LMS berhasil diambil.',
            'data'    => $this->lmsService->dashboardOverview((int) $request->user()->id),
        ]);
    }

    /**
     * Pengaturan level modul LMS (`lms_*` pada `core_system_settings`).
     */
    public function showPengaturanModul(): JsonResponse
    {
        Gate::authorize('lms.pengaturan.read');

        $definitions = collect(LmsModuleSettingService::definitions())
            ->map(fn (array $definition, string $key) => ['key' => $key] + $definition)
            ->values()
            ->all();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan modul LMS berhasil diambil.',
            'data'    => [
                'values'      => $this->lmsModuleSettingService->all(),
                'definitions' => $definitions,
            ],
        ]);
    }

    /**
     * Simpan pengaturan level modul LMS. Perubahan dicatat ke audit log LMS.
     */
    public function updatePengaturanModul(UpdatePengaturanLmsRequest $request): JsonResponse
    {
        Gate::authorize('lms.pengaturan.manage');

        $values = $this->lmsModuleSettingService->update(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan modul LMS berhasil disimpan.',
            'data'    => $values,
        ]);
    }

    /**
     * Pengaturan publik LMS (tanpa autentikasi, cache 24 jam).
     *
     * Hanya key yang ditandai `public` pada definisi yang dikembalikan —
     * tidak ada data sensitif.
     */
    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan publik LMS berhasil diambil.',
            'data'    => $this->lmsModuleSettingService->publicSettings(),
        ]);
    }
}
