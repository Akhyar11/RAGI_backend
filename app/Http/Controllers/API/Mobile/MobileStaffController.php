<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreMobileProposalRequest;
use App\Http\Requests\Mobile\StoreMobilePengajuanRequest;
use App\Http\Requests\Mobile\StoreMobileTiketRequest;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Krs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Pertemuan;
use App\Services\AuditLogService;
use App\Services\Mobile\MobileStaffService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MobileStaffController extends Controller
{
    public function __construct(
        private MobileStaffService $staffService,
        private FileStorageService $fileStorageService
    ) {}

    /**
     * GET /api/v1/mobile/staff/jadwal-mengajar
     * Jadwal mengajar dosen (hari ini & semester berjalan).
     */
    public function jadwalMengajar(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $canManage = $user->isSuperAdmin() || $user->can('siakad.kelas.manage');

        // Pengguna non-admin hanya dapat melihat jadwal mengajarnya sendiri
        $userId = $user->id;
        $username = $canManage ? ($request->query('username') ?? $user->username) : $user->username;
        $nidn = $canManage ? $request->query('nidn') : null;
        $nip = $canManage ? $request->query('nip') : null;

        $hari = $request->query('hari');
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $sortBy = $request->query('sort_by', 'jam_mulai');
        $sortOrder = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';

        $data = $this->staffService->getJadwalMengajar(
            userId: $userId,
            nidn: $nidn,
            nip: $nip,
            username: $username,
            hari: $hari,
            tahunAkademikId: $tahunAkademikId,
            sortBy: $sortBy,
            sortOrder: $sortOrder
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal mengajar berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/home
     * Ringkasan dashboard beranda staf & dosen (Jadwal mengajar hari ini & status operasional).
     */
    public function homeSummary(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $user->loadMissing('roles');
        $schedule = $this->staffService->getJadwalMengajar(
            userId: $user->id,
            username: $user->username
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan beranda staf berhasil dimuat.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $schedule['dosen']['nama_lengkap'],
                    'username' => $user->username,
                    'role' => $user->roles->first()?->name ?? $user->roles->first()?->slug ?? null,
                ],
                'jadwal' => $schedule,
            ],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/avatar
     * Unggah & perbarui foto profil staf / dosen ke storage RAG.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        Gate::authorize('update', $user);

        $request->validate([
            'avatar' => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:5120',
            'avatar_base64' => 'nullable|string',
            'remove_avatar' => 'nullable|boolean',
        ]);

        $path = null;

        if ($request->boolean('remove_avatar')) {
            $oldValues = $user->getOriginal();
            $user->avatar_path = null;
            $user->save();

            try {
                AuditLogService::record(
                    module: 'iam',
                    action: 'remove_avatar',
                    tableName: 'core_users',
                    recordId: $user->id,
                    oldValues: $oldValues,
                    newValues: $user->getChanges(),
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log hapus avatar mobile staff: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Foto profil berhasil dihapus dari server RAG.',
                'data' => null,
            ]);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $path = $this->fileStorageService->store($file, 'avatars', private: false);
        } elseif ($request->filled('avatar_base64')) {
            $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', (string) $request->input('avatar_base64'));
            $decoded = base64_decode($cleanBase64, true);

            if ($decoded === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Format gambar base64 tidak valid.',
                ], 422);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($decoded);
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

            if (!isset($allowedMimes[$mimeType]) || strlen($decoded) > 5242880) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Berkas base64 harus berupa gambar valid (JPEG, PNG, WEBP) dengan ukuran maksimal 5MB.',
                ], 422);
            }

            $ext = $allowedMimes[$mimeType];
            $path = $this->fileStorageService->storeContents($decoded, 'avatars', $ext, private: false);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas atau data avatar wajib dikirimkan.',
            ], 422);
        }

        $oldValues = $user->getOriginal();
        $user->avatar_path = $path;
        $user->save();
        $newValues = $user->getChanges();

        try {
            AuditLogService::record(
                module: 'iam',
                action: 'update_avatar',
                tableName: 'core_users',
                recordId: $user->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log avatar mobile staff: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Foto profil berhasil diperbarui dan tersimpan di server RAG.',
            'data' => [
                'user_id' => $user->id,
                'avatar_url' => $this->fileStorageService->url($path),
                'path' => $path,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // ==========================================
    // 3. INPUT NILAI MAHASISWA (OBE / KHS)
    // ==========================================

    /**
     * GET /api/v1/mobile/staff/nilai/kelas
     */
    public function listKelasNilai(Request $request): JsonResponse
    {
        Gate::authorize('siakad.nilai.read');

        $user = $request->user();
        $data = $this->staffService->getKelasListForGrading(
            userId: $user?->id,
            nidn: $request->query('nidn'),
            nip: $request->query('nip'),
            username: $user?->username
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas penilaian berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/nilai/kelas/{kelasId}
     */
    public function detailKelasNilai(int $kelasId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.nilai.read');

        $data = $this->staffService->getKelasGradeDetail($kelasId);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail komponen nilai kelas berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/nilai/kelas/{kelasId}
     */
    public function saveKelasNilai(int $kelasId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.nilai.manage');

        $request->validate([
            'grades' => 'required|array',
            'grades.*.krs_detail_id' => 'required|integer',
            'grades.*.nilai_harian' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_uts' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_uas' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_praktik' => 'nullable|numeric|min:0|max:100',
            'grades.*.is_final' => 'nullable|boolean',
        ]);

        $gradesInput = $request->input('grades', []);

        $result = $this->staffService->saveKelasGrades(
            kelasId: $kelasId,
            grades: $gradesInput,
            userId: $request->user()?->id,
            request: $request
        );

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    // ==========================================
    // 4. BIMBINGAN AKADEMIK (DPA) & PERSETUJUAN KRS
    // ==========================================

    /**
     * GET /api/v1/mobile/staff/bimbingan-pa/mahasiswa
     */
    public function listMahasiswaBimbingan(Request $request): JsonResponse
    {
        Gate::authorize('siakad.pa.read');

        $user = $request->user();
        $data = $this->staffService->getAdviseesList(
            userId: $user?->id,
            nidn: $request->query('nidn'),
            nip: $request->query('nip'),
            username: $user?->username
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mahasiswa bimbingan PA berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}
     */
    public function detailKrsBimbingan(int $krsId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.krs.read');

        $data = $this->staffService->getKrsDetail($krsId);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'KRS mahasiswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian KRS mahasiswa bimbingan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/approve
     */
    public function approveKrsBimbingan(int $krsId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.krs.approve');

        $krs = Krs::find($krsId);
        $oldValues = $krs ? [
            'status' => $krs->status,
            'disetujui_oleh' => $krs->disetujui_oleh,
            'disetujui_at' => $krs->disetujui_at?->toIso8601String(),
        ] : null;

        $success = $this->staffService->approveKrs($krsId, $request->user()?->id);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'KRS mahasiswa tidak ditemukan atau gagal disetujui.',
            ], 404);
        }

        try {
            AuditLogService::record(
                module: 'siakad',
                action: 'approve_krs',
                tableName: 'siakad_krs',
                recordId: $krsId,
                oldValues: $oldValues,
                newValues: ['krs_id' => $krsId, 'status' => 'disetujui'],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log approve KRS: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'KRS mahasiswa berhasil disetujui & divalidasi.',
            'data' => ['krs_id' => $krsId, 'status' => 'disetujui'],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/reject
     */
    public function rejectKrsBimbingan(int $krsId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.krs.approve');

        $request->validate([
            'alasan' => 'nullable|string|max:500',
        ]);

        $krs = Krs::find($krsId);
        $oldValues = $krs ? [
            'status' => $krs->status,
        ] : null;

        $success = $this->staffService->rejectKrs(
            krsId: $krsId,
            alasan: $request->input('alasan'),
            userId: $request->user()?->id
        );

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'KRS mahasiswa tidak ditemukan atau gagal diproses.',
            ], 404);
        }

        try {
            AuditLogService::record(
                module: 'siakad',
                action: 'reject_krs',
                tableName: 'siakad_krs',
                recordId: $krsId,
                oldValues: $oldValues,
                newValues: [
                    'krs_id' => $krsId,
                    'status' => 'revisi',
                    'alasan' => $request->input('alasan'),
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log reject KRS: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan revisi KRS telah dikirim ke mahasiswa.',
            'data' => ['krs_id' => $krsId, 'status' => 'revisi'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}
     */
    public function listCatatanBimbingan(int $mahasiswaId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.pa.read');

        $data = $this->staffService->getCatatanBimbingan($mahasiswaId);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat catatan bimbingan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}
     */
    public function storeCatatanBimbingan(int $mahasiswaId, Request $request): JsonResponse
    {
        Gate::authorize('siakad.pa.create');

        $request->validate([
            'isi' => 'required|string|min:5|max:2000',
            'kategori' => 'nullable|string|max:50',
            'kesimpulan' => 'nullable|string|max:500',
            'butuh_penanganan_khusus' => 'nullable|boolean',
            'tanggal' => 'nullable|date',
        ]);

        $mahasiswa = Mahasiswa::find($mahasiswaId);
        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa tidak ditemukan.',
            ], 404);
        }

        $dosen = $this->staffService->resolveDosen($request->user()?->id);
        if ($dosen && $mahasiswa->dosen_wali_id && $mahasiswa->dosen_wali_id !== $dosen->id && !$request->user()->can('siakad.pa.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda bukan Dosen Pembimbing Akademik mahasiswa ini.',
            ], 403);
        }

        $data = $this->staffService->storeCatatanBimbingan(
            mahasiswaId: $mahasiswaId,
            data: $request->only(['isi', 'kategori', 'kesimpulan', 'butuh_penanganan_khusus', 'tanggal']),
            userId: $request->user()?->id
        );

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasiswa tidak ditemukan atau Dosen PA tidak terhubung.',
            ], 404);
        }

        try {
            AuditLogService::record(
                module: 'siakad',
                action: 'create_pa_catatan',
                tableName: 'siakad_pa_catatan',
                recordId: $data['id'],
                oldValues: null,
                newValues: $data,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log catatan PA: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan bimbingan akademik berhasil disimpan.',
            'data' => $data,
        ]);
    }

    // ================================================================
    // MODUL PERKULIAHAN: JURNAL PERTEMUAN (BAP) & PRESENSI KELAS
    // ================================================================

    /**
     * GET /api/v1/mobile/staff/perkuliahan/kelas
     * Daftar kelas yang diampu dosen beserta progres pertemuan.
     */
    public function listKelasPertemuan(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $canManage = $user->isSuperAdmin() || $user->can('siakad.kelas.manage');
        $nidn = $canManage ? $request->query('nidn') : null;
        $nip = $canManage ? $request->query('nip') : null;
        $username = $canManage ? ($request->query('username') ?? $user->username) : $user->username;

        $data = $this->staffService->getKelasListForPertemuan(
            userId: $user->id,
            nidn: $nidn,
            nip: $nip,
            username: $username
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kelas perkuliahan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/perkuliahan/kelas/{kelasId}
     * Rincian kelas dan daftar pertemuan 1 s/d 16.
     */
    public function detailPertemuanKelas(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $data = $this->staffService->getPertemuanList($kelasId);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas perkuliahan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar jurnal pertemuan perkuliahan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/perkuliahan/kelas/{kelasId}/mulai
     * Memulai sesi perkuliahan & buka token presensi mahasiswa.
     */
    public function mulaiSesiPertemuan(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $validator = Validator::make($request->all(), [
            'pertemuan_ke' => 'required|integer|min:1|max:16',
            'materi' => 'required|string|min:3|max:255',
            'catatan' => 'nullable|string|max:1000',
            'tanggal' => 'nullable|date',
            'jam_mulai' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi data sesi perkuliahan gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->staffService->mulaiSesiPertemuan(
            kelasId: $kelasId,
            data: $request->only(['pertemuan_ke', 'materi', 'catatan', 'tanggal', 'jam_mulai']),
            userId: $request->user()?->id
        );

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tidak ditemukan atau gagal membuka sesi perkuliahan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi perkuliahan Pertemuan ' . $data['pertemuan_ke'] . ' berhasil dimulai.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/rotate-token
     * Putar ulang token absensi dinamis (anti titip-hadir).
     */
    public function rotateTokenPertemuan(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $data = $this->staffService->rotateTokenPertemuan($pertemuanId, $request->user()?->id);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pertemuan tidak ditemukan atau sesi telah ditutup.',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token absensi berhasil diputar ulang.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/selesai
     * Tutup sesi perkuliahan dan kunci absensi kelas.
     */
    public function selesaiSesiPertemuan(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $data = $this->staffService->selesaiSesiPertemuan($pertemuanId, $request->user()?->id);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pertemuan perkuliahan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi perkuliahan Pertemuan ' . $data['pertemuan_ke'] . ' telah ditutup.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi
     * Daftar absensi mahasiswa di pertemuan kelas.
     */
    public function detailPresensiPertemuan(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $data = $this->staffService->getPresensiPertemuan($pertemuanId);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pertemuan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar presensi mahasiswa berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi
     * Update/override status absensi mahasiswa secara manual oleh dosen.
     */
    public function savePresensiPertemuan(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'items.*.status' => ['required', 'string', Rule::in(AbsensiMahasiswa::STATUSES)],
            'items.*.catatan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi data presensi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->staffService->updatePresensiMahasiswa(
            pertemuanId: $pertemuanId,
            items: $request->input('items', []),
            userId: $request->user()?->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status presensi mahasiswa berhasil diperbarui.',
            'data' => $data,
        ]);
    }

    // =========================================================================
    // 4. GATEWAY MODUL TERPADU (SIPPM, SIKEU, SINAPRA, ARSIP, LMS, SPMB)
    // =========================================================================

    /**
     * GET /api/v1/mobile/staff/sippm/proposal
     * Daftar proposal kegiatan penelitian & pengabdian milik dosen.
     */
    public function listSippmProposal(Request $request): JsonResponse
    {
        Gate::authorize('sippm.proposal.read');

        $user = $request->user();
        $result = $this->staffService->getSippmProposals($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar proposal SIPPM berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/sippm/proposal
     * Ajukan proposal penelitian / pengabdian baru via aplikasi mobile.
     */
    public function storeSippmProposal(StoreMobileProposalRequest $request): JsonResponse
    {
        Gate::authorize('sippm.proposal.create');

        $user = $request->user();
        try {
            $proposal = $this->staffService->storeSippmProposal(
                $user,
                $request->validated(),
                $request->file('file_proposal')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Proposal penelitian/pengabdian berhasil didaftarkan.',
                'data' => $proposal,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/mobile/staff/sippm/luaran
     * Daftar luaran penelitian & publikasi ilmiah / HKI milik dosen.
     */
    public function listSippmLuaran(Request $request): JsonResponse
    {
        Gate::authorize('sippm.proposal.read');

        $user = $request->user();
        $result = $this->staffService->getSippmLuaran($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar luaran penelitian berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/sikeu/payroll
     * Riwayat slip gaji payroll dan potongan bulanan staf.
     */
    public function listSikeuPayroll(Request $request): JsonResponse
    {
        Gate::authorize('simpeg.payroll.read');

        $user = $request->user();
        $result = $this->staffService->getSikeuPayroll($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat slip gaji payroll berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/sikeu/pengajuan
     * Daftar pengajuan keuangan (reimbursement, uang muka, kas bon) milik staf.
     */
    public function listSikeuPengajuan(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.pengajuan.read');

        $user = $request->user();
        $result = $this->staffService->getSikeuPengajuan($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan keuangan berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/sikeu/pengajuan
     * Kirim pengajuan keuangan / reimbursement / operasional baru via mobile.
     */
    public function storeSikeuPengajuan(StoreMobilePengajuanRequest $request): JsonResponse
    {
        Gate::authorize('sikeu.pengajuan.create');

        $user = $request->user();
        $pengajuan = $this->staffService->storeSikeuPengajuan(
            $user,
            $request->validated(),
            $request->file('file_lampiran')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan keuangan berhasil dikirim.',
            'data' => $pengajuan,
        ], 201);
    }

    /**
     * GET /api/v1/mobile/staff/sinapra/tiket
     * Daftar tiket laporan kerusakan sarana dan status perbaikan.
     */
    public function listSinapraTiket(Request $request): JsonResponse
    {
        Gate::authorize('sinapra.maintenance.read');

        $user = $request->user();
        $result = $this->staffService->getSinapraTiket($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tiket sarana berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/sinapra/tiket
     * Laporkan kerusakan sarana / fasilitas kampus baru via mobile.
     */
    public function storeSinapraTiket(StoreMobileTiketRequest $request): JsonResponse
    {
        Gate::authorize('sinapra.maintenance.create');

        $user = $request->user();
        $tiket = $this->staffService->storeSinapraTiket(
            $user,
            $request->validated(),
            $request->file('foto')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan kerusakan berhasil dilaporkan.',
            'data' => $tiket,
        ], 201);
    }

    /**
     * GET /api/v1/mobile/staff/sinapra/peminjaman
     * Daftar status peminjaman ruangan dan aset sarana milik staf.
     */
    public function listSinapraPeminjaman(Request $request): JsonResponse
    {
        Gate::authorize('sinapra.peminjaman_ruangan.read');

        $user = $request->user();
        $result = $this->staffService->getSinapraPeminjaman($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar peminjaman sarana & ruangan berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/arsip/e-file
     * Dokumen digital e-file kepegawaian pribadi milik staf.
     */
    public function listArsipEfile(Request $request): JsonResponse
    {
        Gate::authorize('simpeg.dokumen.read');

        $user = $request->user();
        $result = $this->staffService->getArsipEfile($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dokumen e-file berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/arsip/surat
     * Arsip persuratan dan surat tugas terkait staf.
     */
    public function listArsipSurat(Request $request): JsonResponse
    {
        Gate::authorize('arsip.nomor_surat.read');

        $user = $request->user();
        $result = $this->staffService->getArsipSurat($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar surat masuk & keluar berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/lms/bahan-ajar
     * Daftar materi perkuliahan & RPS kelas yang diampu dosen.
     */
    public function listLmsBahanAjar(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        $result = $this->staffService->getLmsBahanAjar($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar bahan ajar berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/lms/pengumuman
     * Pengumuman kelas dan notifikasi akademik dosen.
     */
    public function listLmsPengumuman(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $user = $request->user();
        $result = $this->staffService->getLmsPengumuman($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengumuman kelas berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/v1/mobile/staff/spmb/referral
     * Kode referral SPMB, statistik, dan daftar calon mahasiswa yang dibawa.
     */
    public function getSpmbReferral(Request $request): JsonResponse
    {
        Gate::authorize('spmb.pendaftaran.read');

        $user = $request->user();
        $result = $this->staffService->getSpmbReferral($user, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Data referral SPMB berhasil dimuat.',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/v1/mobile/staff/spmb/referral
     * Generate / perbarui link share referral eksternal & payload QR.
     */
    public function generateSpmbReferral(Request $request): JsonResponse
    {
        Gate::authorize('spmb.pendaftaran.create');

        $user = $request->user();
        $data = $this->staffService->generateSpmbReferral($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Link referral SPMB berhasil dibuat.',
            'data' => $data,
        ], 201);
    }
}
