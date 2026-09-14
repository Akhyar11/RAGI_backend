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
     * Login karyawan untuk Mobile App (Flutter)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string', // Email atau NIP
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->login)
            ->orWhereHas('pegawai', function ($q) use ($request) {
                $q->where('nip', $request->login);
            })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Kredensial login tidak cocok dengan data kami.'],
            ]);
        }

        $employee = Pegawai::with(['officeLocation', 'shiftTemplate'])->where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Akun karyawan tidak aktif atau belum terdaftar di SIMPEG.',
            ], 403);
        }

        $tokenName = $request->device_name ?? 'flutter-mobile';
        $tokenResult = $user->createToken($tokenName);
        $token = $tokenResult->plainTextToken ?? $tokenResult->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $employee->nama_lengkap ?: $user->username,
                    'email' => $user->email,
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $employee->officeLocation,
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

        $enrolledEmbedding = null;
        if ($employee && !empty($employee->face_embedding)) {
            $decoded = json_decode($employee->face_embedding, true);
            $enrolledEmbedding = is_array($decoded) ? $decoded : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $employee?->nama_lengkap ?: $user->username,
                    'email' => $user->email,
                ],
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $employee->officeLocation,
                ] : null,
                'has_face_enrolled' => $employee ? !empty($employee->face_embedding) : false,
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
        }
        // Opsi B: Vektor embedding langsung
        elseif ($request->has('embedding')) {
            $embedding = $request->input('embedding');
        }

        if (empty($embedding) || !is_array($embedding)) {
            return response()->json([
                'success' => false,
                'message' => 'Format embedding tidak valid atau gagal diekstrak.',
            ], 422);
        }

        $employee->update([
            'face_embedding' => json_encode($embedding),
            'face_enrolled_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran biometrik wajah berhasil disimpan.',
            'data' => [
                'face_enrolled_at' => $employee->face_enrolled_at,
                'dimension' => count($embedding),
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
