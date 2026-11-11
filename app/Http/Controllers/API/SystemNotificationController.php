<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSystemNotificationRequest;
use App\Services\Notifications\SystemNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemNotificationController extends Controller
{
    public function __construct(private readonly SystemNotificationService $notifications) {}

    public function index(IndexSystemNotificationRequest $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $moduleId = $request->filled('module_id') ? $request->integer('module_id') : null;
        $isRead = $request->input('is_read') !== null && $request->input('is_read') !== ''
            ? $request->boolean('is_read')
            : null;
        $paginator = $this->notifications->index($request->user(), [
            'per_page' => $perPage,
            'search' => $search,
            'module_id' => $moduleId,
            'is_read' => $isRead,
            'sort_by' => (string) $request->input('sort_by', 'created_at'),
            'sort_order' => (string) $request->input('sort_order', 'desc'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'module_id' => $moduleId,
                'is_read' => $isRead,
                'sort_by' => $request->input('sort_by', 'created_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
            'data' => ['unread_count' => $this->notifications->unreadCount($request->user())],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        try {
            $notification = $this->notifications->markAsRead($request->user(), $id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $notification,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notifications->markAllAsRead($request->user());

        return response()->json([
            'status' => 'success',
            'message' => $updated > 0 ? 'Notifikasi ditandai sudah dibaca.' : 'Tidak ada notifikasi belum dibaca.',
            'data' => ['updated' => $updated],
        ]);
    }
}
