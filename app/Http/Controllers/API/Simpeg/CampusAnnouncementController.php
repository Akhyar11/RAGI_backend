<?php

namespace App\Http\Controllers\API\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\StoreCampusAnnouncementRequest;
use App\Models\CoreNotification;
use App\Models\Module;
use App\Models\Simpeg\CampusAnnouncement;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampusAnnouncementController extends Controller
{
    /**
     * List broadcasted announcements (Admin SIMPEG).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('simpeg.pegawai.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $category = $request->input('category');
        $targetRole = $request->input('target_role');
        $search = $request->input('search');

        $allowedSorts = ['id', 'created_at', 'event_date', 'priority', 'title'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'id';
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = CampusAnnouncement::query()
            ->with('sender:id,name,username')
            ->orderBy($sortBy, $sortOrder);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($targetRole) {
            $query->where('target_role', $targetRole);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar riwayat broadcast pengumuman berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'filters' => [
                    'search' => $search,
                    'category' => $category,
                    'target_role' => $targetRole,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    /**
     * Broadcast a new announcement / agenda from Admin SIMPEG.
     */
    public function store(StoreCampusAnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetRole = $validated['target_role'];
        $isAgenda = !empty($validated['is_agenda']);
        $priority = $validated['priority'] ?? 'normal';
        $user = $request->user();

        // 1. Tentukan penerima pegawai kampus berbasis relasi pegawai/dosen resmi
        $userQuery = User::query()
            ->where('is_active', true)
            ->whereHas('pegawai');

        if ($targetRole === 'dosen') {
            $userQuery->whereHas('dosen');
        } elseif ($targetRole === 'tendik') {
            $userQuery->whereDoesntHave('dosen');
        }

        $recipientIds = $userQuery->pluck('id')->all();

        $simpegModule = Module::where('code', 'SIMPEG')->first();
        $moduleId = $simpegModule ? $simpegModule->id : null;

        // 2. Simpan record announcement & sebar notifikasi dalam transaksi database
        $announcement = DB::transaction(function () use ($validated, $user, $targetRole, $isAgenda, $priority, $recipientIds, $moduleId) {
            $announcement = CampusAnnouncement::create([
                'sender_id' => $user->id,
                'title' => $validated['title'],
                'category' => $validated['category'],
                'target_role' => $targetRole,
                'content' => $validated['content'],
                'is_agenda' => $isAgenda,
                'event_date' => $validated['event_date'] ?? null,
                'event_time' => $validated['event_time'] ?? null,
                'event_location' => $validated['event_location'] ?? null,
                'event_url' => $validated['event_url'] ?? null,
                'priority' => $priority,
                'recipients_count' => count($recipientIds),
            ]);

            $now = now();
            $batchData = [];

            foreach ($recipientIds as $userId) {
                $batchData[] = [
                    'user_id' => $userId,
                    'module_id' => $moduleId,
                    'type' => 'simpeg_announcement',
                    'title' => $announcement->title,
                    'message' => $announcement->content,
                    'url' => $announcement->event_url ?? '/notifications',
                    'entity_type' => 'CampusAnnouncement',
                    'entity_id' => $announcement->id,
                    'priority' => $priority,
                    'is_read' => false,
                    'dedup_key' => (string) Str::uuid(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert chunks of 100 via CoreNotification model
            foreach (array_chunk($batchData, 100) as $chunk) {
                CoreNotification::insert($chunk);
            }

            return $announcement;
        });

        // 3. Audit Log
        try {
            AuditLogService::record(
                module: 'simpeg',
                action: 'broadcast_announcement',
                tableName: 'simpeg_campus_announcements',
                recordId: $announcement->id,
                oldValues: null,
                newValues: $announcement->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log broadcast announcement: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Pengumuman berhasil disiarkan ke " . count($recipientIds) . " pegawai (" . $this->formatTargetRoleLabel($targetRole) . ").",
            'data' => $announcement,
        ], 201);
    }

    /**
     * Today's announcements and upcoming agendas for Mobile Staff.
     */
    public function todayAgendas(Request $request): JsonResponse
    {
        Gate::authorize('simpeg.pegawai.read');

        $user = $request->user();
        $pegawai = $user ? $user->pegawai : null;
        $jenisPegawai = $pegawai ? ($pegawai->jenis_pegawai ?: 'Tendik') : null;
        if (!$jenisPegawai && $user?->dosen()->exists()) {
            $jenisPegawai = 'Dosen';
        }
        $userRole = strtolower($jenisPegawai ?? 'tendik') === 'dosen' ? 'dosen' : 'tendik';

        // 1. Seragam Hari Ini
        $today = now()->toDateString();
        $seragam = CampusAnnouncement::query()
            ->forRole($userRole)
            ->where('category', 'seragam')
            ->where(function ($q) use ($today) {
                $q->whereNull('event_date')
                  ->orWhereDate('event_date', $today);
            })
            ->latest('id')
            ->first();

        // 2. Agenda Rapat / Kegiatan Hari Ini & Mendatang
        $agendas = CampusAnnouncement::query()
            ->forRole($userRole)
            ->where('is_agenda', true)
            ->whereDate('event_date', '>=', $today)
            ->orderBy('event_date', 'asc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Agenda & pengumuman berhasil diambil.',
            'data' => [
                'user_role' => $userRole,
                'seragam_hari_ini' => $seragam,
                'upcoming_agendas' => $agendas,
            ],
        ]);
    }

    private function formatTargetRoleLabel(string $role): string
    {
        return match ($role) {
            'dosen' => 'Dosen Saja',
            'tendik' => 'Tendik / Staff Saja',
            default => 'Semua Pegawai (Dosen & Tendik)',
        };
    }
}
