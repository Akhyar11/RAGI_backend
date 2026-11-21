<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Mobile\MobileStaffService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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
        $canManage = $user && ($user->isSuperAdmin() || $user->can('siakad.kelas.manage'));

        // Pengguna non-admin hanya dapat melihat jadwal mengajarnya sendiri
        $userId = $user?->id;
        $username = $canManage ? ($request->query('username') ?? $user?->username) : $user?->username;
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
        ]);

        $path = null;

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
}
