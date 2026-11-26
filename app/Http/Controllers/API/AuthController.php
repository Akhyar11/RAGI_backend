<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use App\Services\PythonFaceService;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected PythonFaceService $faceService,
        protected ?FileStorageService $fileStorageService = null
    ) {}

    protected function resolveAvatarUrl(User $user): ?string
    {
        if (empty($user->avatar_path)) {
            return null;
        }
        $storage = $this->fileStorageService ?? app(FileStorageService::class);
        $resolved = $storage->url($user->avatar_path);
        if (!$resolved) {
            return null;
        }
        return str_starts_with($resolved, '/') ? url($resolved) : $resolved;
    }

    /**
     * Cari pegawai SIMPEG yang benar-benar tertaut dengan akun.
     * Tidak membuat profil rekaan jika data pegawai belum tersedia.
     */
    protected function resolveEmployee($user): ?Pegawai
    {
        $relations = ['officeLocation', 'shiftTemplate'];
        $employee = Pegawai::with($relations)->where('user_id', $user->id)->first();

        if (!$employee && !empty($user->email)) {
            $employee = Pegawai::with($relations)->where('email', $user->email)->first();
            if ($employee) {
                $employee->update(['user_id' => $user->id]);
                $employee->load($relations);
            }
        }

        return $employee;
    }

    /**
     * Login karyawan untuk Mobile App (Flutter / Android / iOS)
     */
    public function login(Request $request): JsonResponse
    {
        // 1. Fleksibilitas key identifier dari aplikasi mobile
        $loginInput = $request->input('login')
            ?? $request->input('username')
            ?? $request->input('email')
            ?? $request->input('identifier');

        $password = $request->input('password');

        if (empty($loginInput) || empty($password)) {
            throw ValidationException::withMessages([
                'login' => ['Kredensial login (email/username/NIP) dan password wajib diisi.'],
            ]);
        }

        // 2. Pencarian multi-identifier: email, username, NIP, NIDN, NUPTK, NIK
        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->orWhereHas('pegawai', function ($q) use ($loginInput) {
                $q->where('nip', $loginInput)
                    ->orWhere('nidn', $loginInput)
                    ->orWhere('nuptk', $loginInput)
                    ->orWhere('nik', $loginInput);
            })->first();

        // Dukung pencarian relasi Dosen SIAKAD jika diperlukan
        if (!$user && class_exists(\App\Models\Siakad\Dosen::class)) {
            $dosen = \App\Models\Siakad\Dosen::where('nidn', $loginInput)
                ->orWhere('nip', $loginInput)
                ->first();
            if ($dosen && $dosen->user_id) {
                $user = User::find($dosen->user_id);
            }
        }

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Kredensial login tidak cocok dengan data kami.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Akun Anda berstatus non-aktif. Silakan hubungi administrator.'],
            ]);
        }

        // 3. Resolusi Profil Pegawai (SIMPEG) dari data nyata.
        $employee = $this->resolveEmployee($user);
        if (!$employee) {
            throw ValidationException::withMessages([
                'login' => ['Akun belum tertaut dengan data pegawai SIMPEG. Hubungi administrator.'],
            ]);
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();
        $shift = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();

        // 4. Penerbitan Token & Response Terstandarisasi
        $tokenName = $request->device_name ?? 'mobile-absen';
        $tokenResult = $user->createToken($tokenName);
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => [
                'token' => $token,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $employee->nama_lengkap ?: $user->username,
                    'username' => $user->username,
                    'email' => $user->email,
                    'referral_code' => $user->referral_code,
                    'avatar_url' => $this->resolveAvatarUrl($user),
                    'roles' => $user->roles->pluck('slug')->values(),
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'nip' => $employee->nip,
                    'nidn' => $employee->nidn,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'face_enrolled_at' => $employee->face_enrolled_at,
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $office,
                    'shift' => $shift,
                ],
            ],
        ]);
    }

    /**
     * Ambil data profil karyawan yang sedang login
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);
        if (!$employee) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Akun belum tertaut dengan data pegawai SIMPEG. Hubungi administrator.',
                ], 403)
            );
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();
        $shift = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?: ($employee->nama_lengkap ?: $user->username),
                    'username' => $user->username,
                    'email' => $user->email,
                    'referral_code' => $user->referral_code,
                    'avatar_url' => $this->resolveAvatarUrl($user),
                    'roles' => $user->roles->pluck('slug')->values(),
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'nip' => $employee->nip,
                    'nidn' => $employee->nidn,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'face_enrolled_at' => $employee->face_enrolled_at,
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $office,
                    'shift' => $shift,
                ],
                'has_face_enrolled' => !empty($employee->face_embedding),
            ],
        ]);
    }

    /**
     * Pencatatan persetujuan eksplisit karyawan (Consent UU PDP)
     */
    public function recordConsent(Request $request): JsonResponse
    {
        $request->validate([
            'consent' => 'required|boolean|accepted',
        ]);

        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();
        $employee->update([
            'consent_pdp_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Persetujuan pengumpulan data biometrik (UU PDP) berhasil dicatat.',
            'data' => [
                'consent_pdp_at' => $employee->consent_pdp_at,
            ],
        ]);
    }

    /**
     * Pendaftaran embedding wajah karyawan (Multi-Shot maupun Single-Shot)
     */
    public function enrollFace(Request $request): JsonResponse
    {
        if (!$request->has('photos') && !$request->has('images')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wajib menyertakan 3 sampel foto wajah (photos) untuk pendaftaran biometrik.',
            ], 422);
        }

        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();

        if (!$employee->consent_pdp_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wajib menyetujui formulir consent UU PDP sebelum mendaftarkan biometrik wajah.',
            ], 422);
        }

        $embedding = null;

        // Opsi A: Multi-shot foto wajah via microservice Python port 8001
        if ($request->has('photos') || $request->has('images')) {
            $photos = $request->input('photos', $request->input('images'));
            if (!is_array($photos) || count($photos) < 3) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Minimal 3 foto pose wajah diperlukan (tegak, nunduk, mendongak).',
                ], 422);
            }

            $enrollResult = $this->faceService->enrollFromImages($photos);
            if (!$enrollResult['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $enrollResult['message'] ?? 'Gagal mengekstraksi wajah di server AI Python.',
                ], 422);
            }

            $embedding = $enrollResult['centroid_embedding'];
            $poseEmbeddings = $enrollResult['embeddings'] ?? [];
            $poseDiversity = $enrollResult['pose_diversity'] ?? [];

            // Gerbang diversitas pose (mengikuti project Presensi Indonusa):
            // tolak jika semua sampel hampir identik, minta rekam ulang
            // bervariasi: tegak hadap kamera, nunduk sedikit, dongak sedikit.
            $minPair = isset($poseDiversity['min_pairwise_similarity'])
                ? (float) $poseDiversity['min_pairwise_similarity']
                : null;
            if (count($poseEmbeddings) >= 2 && $minPair !== null && $minPair > 0.97) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'POSE_NOT_DIVERSE',
                    'message' => 'Foto pendaftaran terlalu mirip (pose tidak bervariasi). Ulangi dengan 3 pose: tegak hadap kamera, nunduk sedikit, dan dongak sedikit.',
                    'pose_diversity' => $poseDiversity,
                ], 422);
            }
        }
        // Vektor embedding langsung tidak diterima: semua sampel wajib
        // diekstrak dan diverifikasi oleh microservice Python.

        if (empty($embedding) || !is_array($embedding)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format embedding tidak valid atau gagal diekstrak.',
            ], 422);
        }

        $employee->update([
            'face_embedding' => json_encode($embedding),
            'face_embeddings' => !empty($poseEmbeddings) ? json_encode(array_values($poseEmbeddings)) : null,
            'face_enrolled_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran biometrik wajah berhasil disimpan.',
            'data' => [
                'face_enrolled_at' => $employee->face_enrolled_at,
                'dimension' => count($embedding),
                'samples_received' => count($poseEmbeddings),
                'pose_diversity' => $poseDiversity,
            ],
        ]);
    }

    /**
     * Reset data biometrik wajah
     */
    public function resetFace(Request $request): JsonResponse
    {
        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();
        $employee->update([
            'face_embedding' => null,
            'face_embeddings' => null,
            'face_enrolled_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data biometrik wajah berhasil direset.',
            'data' => null,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'token')) {
            $user->token()?->revoke();
        } elseif ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil',
            'data' => null,
        ]);
    }
}
