<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Lms\AjukanIzinRequest;
use App\Models\Lms\ForumTopik;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Mobile\MobileMahasiswaService;
use App\Services\Siakad\ForumService;
use App\Services\Siakad\LmsService;
use App\Services\Siakad\QuizService;
use App\Services\Sikeu\MahasiswaCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobileMahasiswaController extends Controller
{
    public function __construct(
        private MobileMahasiswaService $mobileService,
        private LmsService $lmsService,
        private QuizService $quizService,
        private ForumService $forumService,
        private MahasiswaCheckoutService $checkoutService,
    ) {}

    /**
     * POST /api/v1/mobile/mahasiswa/auth/login
     * Login khusus aplikasi mobile mahasiswa (MyIn-U Student) tanpa gate Turnstile web.
     */
    public function login(Request $request): JsonResponse
    {
        $loginInput = trim((string) (
            $request->input('identifier')
            ?? $request->input('login')
            ?? $request->input('username')
            ?? $request->input('email')
            ?? $request->input('nim')
            ?? ''
        ));
        $password = (string) ($request->input('password') ?? '');

        if ($loginInput === '' || $password === '') {
            throw ValidationException::withMessages([
                'identifier' => ['NIM / email / username dan kata sandi wajib diisi.'],
            ]);
        }

        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user) {
            $mhsByNim = Mahasiswa::where('nim', $loginInput)->first();
            if ($mhsByNim && $mhsByNim->user_id) {
                $user = User::find($mhsByNim->user_id);
            }
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Kredensial login tidak cocok dengan data kami.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'identifier' => ['Akun Anda berstatus non-aktif. Silakan hubungi administrator.'],
            ]);
        }

        if (! $user->hasRole('mahasiswa') && ! Mahasiswa::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'identifier' => ['Akun ini tidak memiliki akses ke aplikasi mobile mahasiswa.'],
            ]);
        }

        $profilMahasiswa = $this->mobileService->getProfilSiakad($user->id);

        $tokenName = (string) ($request->input('device_name') ?: 'myinu-student-mobile');
        $tokenResult = $user->createToken($tokenName);
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        try {
            $user->update(['last_login_at' => now()]);
            AuditLogService::record(
                module: 'IAM',
                action: 'login',
                tableName: 'iam_users',
                recordId: $user->id,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log login mobile mahasiswa: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login mahasiswa berhasil.',
            'data' => [
                'token' => $token,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $profilMahasiswa['nama_lengkap'] ?? $user->username,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('slug')->values(),
                ],
                'mahasiswa' => $profilMahasiswa,
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/home
     * Beranda terpadu aplikasi mobile mahasiswa (Profil, Periode Aktif, Sesi Presensi Aktif 30d, Jadwal, LMS, & SIKEU).
     */
    public function home(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->mobileService->getHomeOverview($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data beranda aplikasi mobile mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/periode
     * Daftar periode akademik (default menandai periode aktif `is_active = true`).
     */
    public function periode(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $data = $this->mobileService->listPeriodeAkademik();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar periode akademik berhasil diambil.',
            'data' => $data,
        ]);
    }

    /* =====================================================================
     | A. MODUL PRESENSI MOBILE (QR SCANNER 30 DETIK, IZIN/SAKIT, & REKAP)
     | ===================================================================== */

    /**
     * GET /api/v1/mobile/mahasiswa/presensi/sesi-aktif
     */
    public function sesiPresensiAktif(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->lmsService->assertPresensiFiturAktif($request->user());
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->lmsService->listSesiPresensiAktifMahasiswa($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar sesi presensi aktif berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/presensi/scan
     */
    public function scanPresensi(Request $request): JsonResponse
    {
        Gate::authorize('lms.presensi.create');
        $this->lmsService->assertPresensiFiturAktif($request->user());
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $validated = $request->validate([
            'qr_payload' => ['nullable', 'string', 'max:255'],
            'pertemuan_id' => ['nullable', 'integer', 'min:1'],
            'token' => ['nullable', 'string', 'max:32'],
            'device_info' => ['nullable', 'string', 'max:190'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $absensi = $this->lmsService->scanTokenAbsensi($request->user()->id, $validated);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'siakad_absensi_mahasiswa',
                recordId: (int) ($absensi['pertemuan_id'] ?? 0),
                oldValues: null,
                newValues: $absensi
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log scan presensi mobile: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil dicatat melalui QR/Token scanner.',
            'data' => $absensi,
        ]);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/presensi/check-in
     * Alias self check-in mahasiswa untuk aplikasi mobile.
     * Kontrak, otorisasi, validasi, audit, dan logika identik dengan scanPresensi().
     */
    public function checkInPresensi(Request $request): JsonResponse
    {
        return $this->scanPresensi($request);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/presensi/pertemuan/{pertemuanId}/izin
     * Berkas surat izin/sakit disimpan di private disk (local / r2-private) via FileStorageService::store(..., private: true).
     */
    public function ajukanIzin(AjukanIzinRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('lms.presensi.create');
        $this->lmsService->assertPresensiFiturAktif($request->user());
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        // Berkas file_surat disimpan ke private storage (private: true) di dalam LmsService::ajukanIzin via FileStorageService
        $izin = $this->lmsService->ajukanIzin(
            $pertemuanId,
            (int) $request->user()->id,
            $request->validated(),
            $request->file('file_surat')
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'siakad_izin_absensi',
                recordId: (int) ($izin->id ?? $pertemuanId),
                oldValues: null,
                newValues: is_array($izin) ? $izin : $izin->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pengajuan izin mobile: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan izin absensi berhasil diajukan.',
            'data' => $izin,
        ], 201);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/presensi/rekap
     */
    public function rekapPresensi(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->lmsService->assertPresensiFiturAktif($request->user());
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->mobileService->getRekapPresensiMahasiswa($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap kehadiran perkuliahan mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /* =====================================================================
     | B. MODUL LMS MOBILE (OVERVIEW, KELAS, PERTEMUAN, TUGAS, TRYOUT, FORUM)
     | ===================================================================== */

    /**
     * GET /api/v1/mobile/mahasiswa/lms/overview
     */
    public function lmsOverview(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->lmsService->dashboardOverview($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan LMS mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/kelas
     */
    public function lmsKelas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
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
            'status' => 'success',
            'message' => 'Daftar kelas LMS mahasiswa berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/kelas/{kelasId}
     */
    public function lmsKelasDetail(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $data = $this->lmsService->getKelasOverview($kelasId, (int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kelas LMS berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/pertemuan/{pertemuanId}
     */
    public function lmsPertemuanDetail(Request $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $data = $this->lmsService->getPertemuanDetail($pertemuanId, (int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pertemuan LMS berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/tugas
     */
    public function lmsTugas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $allowedSorts = ['id', 'judul', 'deadline_at', 'created_at', 'updated_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $paginator = $this->lmsService->getMyAllTugas(
            (int) $request->user()->id,
            $perPage,
            $search,
            $sortBy,
            $sortOrder
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tugas LMS mahasiswa berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/tryout
     */
    public function lmsTryout(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $userId = (int) $request->user()->id;
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $allowedSorts = ['id', 'judul', 'durasi_menit', 'dibuka_at', 'ditutup_at', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;
        $onlyStandalone = $request->filled('standalone')
            ? filter_var($request->input('standalone'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $paginator = $this->quizService->listTryoutAggregate(
            $userId,
            $this->lmsService->resolveAccessibleKelasIds($userId),
            false,
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $kelasId,
            $onlyStandalone
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tryout & ujian khusus mahasiswa berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/lms/forum
     */
    public function lmsForum(Request $request): JsonResponse
    {
        Gate::authorize('lms.forum.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $userId = (int) $request->user()->id;
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        [$sortBy, $sortOrder] = $this->forumService->normalisasiSort(
            (string) $request->input('sort_by', 'id'),
            (string) $request->input('sort_order', 'desc'),
            ForumService::SORT_TOPIK
        );
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;
        $isPinned = null;
        if ($request->has('is_pinned')) {
            $parsed = filter_var($request->input('is_pinned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $isPinned = $parsed === null ? null : $parsed;
        }

        $paginator = $this->forumService->listTopikAggregate(
            $this->lmsService->resolveAccessibleKelasIds($userId),
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $kelasId,
            $isPinned
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar topik forum diskusi berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /* =====================================================================
     | C. MODUL SIAKAD MOBILE (PROFIL & KTM, JADWAL, KRS, KHS, TRANSKRIP)
     | ===================================================================== */

    /**
     * GET /api/v1/mobile/mahasiswa/siakad/profil
     */
    public function siakadProfil(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $data = $this->mobileService->getProfilSiakad($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil akademik & KTM digital mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/siakad/jadwal
     */
    public function siakadJadwal(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->mobileService->getJadwalKuliah($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal kuliah mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/siakad/krs
     */
    public function siakadKrs(Request $request): JsonResponse
    {
        Gate::authorize('siakad.krs.read');
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->mobileService->getKrsMahasiswa($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data KRS mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/siakad/khs
     */
    public function siakadKhs(Request $request): JsonResponse
    {
        Gate::authorize('siakad.nilai.read');
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;
        $data = $this->mobileService->getKhsMahasiswa($request->user()->id, $tahunAkademikId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data KHS mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/siakad/transkrip
     */
    public function siakadTranskrip(Request $request): JsonResponse
    {
        Gate::authorize('siakad.nilai.read');
        $data = $this->mobileService->getTranskripMahasiswa($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Transkrip nilai sementara mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /* =====================================================================
     | D. MODUL SIKEU MOBILE (RINGKASAN, TAGIHAN, PEMBAYARAN, BEASISWA, DISPENSASI)
     | ===================================================================== */

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/ringkasan
     */
    public function sikeuRingkasan(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.tagihan.read');
        $data = $this->mobileService->getSikeuRingkasan($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Ringkasan keuangan mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/tagihan
     */
    public function sikeuTagihan(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.tagihan.read');
        $status = $request->query('status');
        $data = $this->mobileService->getSikeuTagihan($request->user()->id, $status);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tagihan & invoice mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/pembayaran
     */
    public function sikeuPembayaran(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.tagihan.read');
        $data = $this->mobileService->getSikeuPembayaran($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat pembayaran mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/beasiswa
     */
    public function sikeuBeasiswa(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.tagihan.read');
        $data = $this->mobileService->getSikeuBeasiswa($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar beasiswa mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/dispensasi
     */
    public function sikeuDispensasi(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.dispensasi.read');
        $data = $this->mobileService->getSikeuDispensasi($request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dispensasi pembayaran mahasiswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/mobile/mahasiswa/sikeu/payment-channels
     * Kanal pembayaran aktif yang dikonfigurasi admin (Midtrans / Xendit / BSN H2H).
     */
    public function sikeuPaymentChannels(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.tagihan.read');
        $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $data = $this->checkoutService->getPaymentChannels();

        return response()->json([
            'status' => 'success',
            'message' => 'Kanal pembayaran aktif berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/sikeu/invoice-batch
     * Checklist tagihan mahasiswa -> buat invoice gabungan & VA (termasuk BSN).
     */
    public function sikeuInvoiceBatch(Request $request): JsonResponse
    {
        Gate::authorize('sikeu.pembayaran.create');
        $mhs = $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);

        $validated = $request->validate([
            'tagihan_ids'   => ['nullable', 'array'],
            'tagihan_ids.*' => ['integer', 'exists:sikeu_tagihan_mahasiswa,id'],
            'tagihan_id'    => ['nullable', 'integer', 'exists:sikeu_tagihan_mahasiswa,id'],
            'bank_kode'     => ['nullable', 'string', 'max:20'],
        ]);

        $tagihanIds = (array) ($validated['tagihan_ids'] ?? []);
        if (empty($tagihanIds) && !empty($validated['tagihan_id'])) {
            $tagihanIds = [(int) $validated['tagihan_id']];
        }
        $bankCode = (string) ($validated['bank_kode'] ?? 'BSN');

        $invoice = $this->checkoutService->generateBatchInvoice($mhs->id, $tagihanIds, $bankCode);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice berhasil dibuat.',
            'data' => $invoice,
        ], 201);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/sikeu/tagihan/{id}/terbitkan-h2h
     * Terbitkan billing H2H BSN milik mahasiswa yang sedang login.
     */
    public function sikeuTerbitkanH2h(Request $request, int $id): JsonResponse
    {
        Gate::authorize('sikeu.pembayaran.create');
        $mhs = $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $res = $this->checkoutService->terbitkanH2h($mhs, $id, $request->boolean('force'));
        $hasil = $res['hasil'];

        return response()->json([
            'status' => 'success',
            'message' => !empty($hasil['reused'])
                ? "Billing H2H sudah terbit (CUSTID {$hasil['custid']})."
                : "Billing H2H terbit. CUSTID {$hasil['custid']} siap dibayar via BSN.",
            'data' => $hasil + ['tagihan' => $res['tagihan']],
        ]);
    }

    /**
     * POST /api/v1/mobile/mahasiswa/sikeu/tagihan/{id}/checkout
     * Buat transaksi Payment Gateway aktif (Midtrans Snap / Xendit) untuk tagihan mahasiswa.
     */
    public function sikeuCheckoutGateway(Request $request, int $id): JsonResponse
    {
        Gate::authorize('sikeu.pembayaran.create');
        $mhs = $this->mobileService->resolveAuthenticatedMahasiswa($request->user()->id);
        $data = $this->checkoutService->checkoutGateway($mhs, $id, $request->user()->email);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi pembayaran berhasil dibuat.',
            'data' => $data,
        ], 201);
    }
}
