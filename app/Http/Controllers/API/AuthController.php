<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use App\Services\PythonFaceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected PythonFaceService $faceService
    ) {}

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

        // 3. Resolusi Profil Pegawai (SIMPEG)
        $employee = Pegawai::with(['officeLocation', 'shiftTemplate'])->where('user_id', $user->id)->first();

        // Jika belum tertaut, hubungkan pegawai yang sesuai atau buatkan profil default
        if (!$employee) {
            $defaultOffice = \App\Models\OfficeLocation::where('is_active', true)->first();
            $defaultShift = \App\Models\ShiftTemplate::where('is_active', true)->first();
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();

            $nip = '19' . date('ymd') . rand(100000, 999999);
            $nama = match ($user->username) {
                'admin' => 'Dr. Wasis Utama, M.T.',
                'dosen' => 'Anisa Rahmawati, M.Kom.',
                'tendik' => 'Rahmat Hidayat, S.Kom.',
                'wasis' => 'Dr. Wasis Utama, M.T.',
                'admin_simpeg' => 'Admin Kepegawaian SIMPEG',
                default => ($user->name ?: ucfirst($user->username))
            };

            $employee = Pegawai::create([
                'user_id' => $user->id,
                'unit_kerja_id' => $unitKerja?->id,
                'office_location_id' => $defaultOffice?->id,
                'shift_template_id' => $defaultShift?->id,
                'nip' => $nip,
                'nama_lengkap' => $nama,
                'jenis_kelamin' => 'L',
                'jenis_pegawai' => ($user->hasRole('tendik') || str_contains($user->username, 'tendik')) ? 'tendik' : 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'telepon' => $user->phone ?: '081234567890',
                'alamat' => 'Kampus Terpadu',
                'is_active' => true,
            ]);
            $employee->load(['officeLocation', 'shiftTemplate']);
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();
        $shift = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();

        // 4. Penerbitan Token & Response Terstandarisasi
        $tokenName = $request->device_name ?? 'mobile-absen';
        $tokenResult = $user->createToken($tokenName);
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        return response()->json([
            'status' => 'success',
            'success' => true,
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
        $employee = Pegawai::with(['officeLocation', 'shiftTemplate'])->where('user_id', $user->id)->first();

        if (!$employee) {
            $defaultOffice = \App\Models\OfficeLocation::where('is_active', true)->first();
            $defaultShift = \App\Models\ShiftTemplate::where('is_active', true)->first();
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();

            $employee = Pegawai::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'unit_kerja_id' => $unitKerja?->id,
                'office_location_id' => $defaultOffice?->id,
                'shift_template_id' => $defaultShift?->id,
                'nip' => '19' . date('ymd') . rand(100000, 999999),
                'nama_lengkap' => $user->username,
                'jenis_kelamin' => 'L',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'is_active' => true,
            ]);
            $employee->load(['officeLocation', 'shiftTemplate']);
        }

        $enrolledEmbedding = null;
        if (!empty($employee->face_embedding)) {
            $decoded = json_decode($employee->face_embedding, true);
            $enrolledEmbedding = is_array($decoded) ? $decoded : null;
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();
        $shift = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?: ($employee->nama_lengkap ?: $user->username),
                    'username' => $user->username,
                    'email' => $user->email,
                    'referral_code' => $user->referral_code,
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
                'face_embedding' => $enrolledEmbedding,
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
            'success' => true,
            'message' => 'Persetujuan pengumpulan data biometrik (UU PDP) berhasil dicatat.',
            'consent_pdp_at' => $employee->consent_pdp_at,
        ]);
    }

    /**
     * Pendaftaran embedding wajah karyawan (Multi-Shot maupun Single-Shot)
     */
    public function enrollFace(Request $request): JsonResponse
    {
        if (!$request->has('photos') && !$request->has('images') && !$request->has('embedding')) {
            return response()->json([
                'success' => false,
                'message' => 'Wajib menyertakan sampel foto (photos) atau data biometrik wajah (embedding).',
            ], 422);
        }

        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();

        if (!$employee->consent_pdp_at) {
            return response()->json([
                'success' => false,
                'message' => 'Wajib menyetujui formulir consent UU PDP sebelum mendaftarkan biometrik wajah.',
            ], 422);
        }

        $embedding = null;

        // Opsi A: Multi-shot foto wajah via microservice Python port 8001
        if ($request->has('photos') || $request->has('images')) {
            $photos = $request->input('photos', $request->input('images'));
            if (!is_array($photos) || count($photos) < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimal 1 foto wajah diperlukan untuk pendaftaran biometrik.',
                ], 422);
            }

            $enrollResult = $this->faceService->enrollFromImages($photos);
            if (!$enrollResult['success']) {
                return response()->json([
                    'success' => false,
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
                    'success' => false,
                    'error_code' => 'POSE_NOT_DIVERSE',
                    'message' => 'Foto pendaftaran terlalu mirip (pose tidak bervariasi). Ulangi dengan 3 pose: tegak hadap kamera, nunduk sedikit, dan dongak sedikit.',
                    'pose_diversity' => $poseDiversity,
                ], 422);
            }
        }
        // Opsi B: Vektor embedding langsung
        elseif ($request->has('embedding')) {
            $embedding = $request->input('embedding');
            $poseEmbeddings = [];
            $poseDiversity = [];
        }

        if (empty($embedding) || !is_array($embedding)) {
            return response()->json([
                'success' => false,
                'message' => 'Format embedding tidak valid atau gagal diekstrak.',
            ], 422);
        }

        $employee->update([
            'face_embedding' => json_encode($embedding),
            'face_embeddings' => !empty($poseEmbeddings) ? json_encode(array_values($poseEmbeddings)) : null,
            'face_enrolled_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
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
            'success' => true,
            'message' => 'Data biometrik wajah berhasil direset.',
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
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}
