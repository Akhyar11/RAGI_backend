<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Services\Siakad\LmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint notifikasi in-app modul LMS (Fase 3A).
 *
 * Notifikasi bersifat personal (per `user_id`), sehingga tidak memakai
 * permission terpisah: setiap user hanya dapat membaca dan mengubah status
 * notifikasi miliknya sendiri.
 */
class LmsNotifikasiController extends Controller
{
    public function __construct(protected LmsNotificationService $lmsNotificationService) {}

    /**
     * Daftar notifikasi milik user yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $jenis = $request->filled('jenis') ? (string) $request->input('jenis') : null;
        $isRead = $request->has('is_read') && $request->input('is_read') !== ''
            ? $request->boolean('is_read')
            : null;
        $sortBy = (string) $request->input('sort_by', 'created_at');
        $sortOrder = (string) $request->input('sort_order', 'desc');

        $paginator = $this->lmsNotificationService->index(
            $request->user(),
            [
                'search'     => $search,
                'jenis'      => $jenis,
                'is_read'    => $isRead,
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar notifikasi berhasil diambil.',
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
                'jenis'      => $jenis,
                'is_read'    => $isRead,
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Jumlah notifikasi yang belum dibaca.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
            'data'    => [
                'unread_count' => $this->lmsNotificationService->unreadCount($request->user()),
            ],
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notifikasi = $this->lmsNotificationService->markAsRead($request->user(), $id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data'    => $notifikasi,
        ]);
    }

    /**
     * Tandai seluruh notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $jumlah = $this->lmsNotificationService->markAllAsRead($request->user());

        return response()->json([
            'status'  => 'success',
            'message' => $jumlah > 0
                ? $jumlah.' notifikasi ditandai sudah dibaca.'
                : 'Tidak ada notifikasi yang belum dibaca.',
            'data'    => [
                'updated' => $jumlah,
            ],
        ]);
    }
}
