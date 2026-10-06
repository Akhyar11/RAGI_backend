<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Lms\ForumPost;
use App\Models\Lms\ForumTopik;
use App\Http\Requests\Siakad\Lms\StoreForumPostRequest;
use App\Http\Requests\Siakad\Lms\StoreForumTopikRequest;
use App\Services\AuditLogService;
use App\Services\Siakad\ForumService;
use App\Services\Siakad\LmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ForumController extends Controller
{
    public function __construct(
        protected ForumService $forumService,
        protected LmsService $lmsService
    ) {}

    /**
     * Daftar topik forum dari seluruh kelas yang bisa diakses user (agregat module-level).
     *
     * Endpoint ini read-only: TIDAK membuat topik "Diskusi Umum" otomatis seperti
     * pada endpoint per-kelas, sehingga aman dipanggil berulang kali.
     */
    public function listTopikSaya(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ForumTopik::class);

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        [$sortBy, $sortOrder] = $this->forumService->normalisasiSort(
            (string) $request->input('sort_by', 'id'),
            (string) $request->input('sort_order', 'desc'),
            ForumService::SORT_TOPIK
        );
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;
        // Filter boolean dinormalisasi lewat filter_var dengan NULL_ON_FAILURE:
        // input absen berarti "semua", input tak dikenal diabaikan diam-diam.
        $isPinned = null;
        if ($request->has('is_pinned')) {
            $parsed = filter_var($request->input('is_pinned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $isPinned = $parsed === null ? null : $parsed;
        }

        $paginator = $this->forumService->listTopikAggregate(
            $this->lmsService->resolveAccessibleKelasIds((int) $request->user()->id),
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $kelasId,
            $isPinned
        );
        $paginator->appends($request->query());

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar topik forum berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
                'filters'      => [
                    'search'            => $search,
                    'sort_by'           => $sortBy,
                    'sort_order'        => $sortOrder,
                    'tahun_akademik_id' => $tahunAkademikId,
                    'kelas_id'          => $kelasId,
                    'is_pinned'         => $isPinned,
                ],
            ],
        ]);
    }

    /**
     * Daftar topik forum satu kelas (otomatis buat "Diskusi Umum" bila kosong).
     */
    public function listTopik(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('viewAny', ForumTopik::class);

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        [$sortBy, $sortOrder] = $this->forumService->normalisasiSort(
            (string) $request->input('sort_by', 'id'),
            (string) $request->input('sort_order', 'asc'),
            ForumService::SORT_TOPIK
        );
        $pertemuanId = $request->filled('pertemuan_id') ? (int) $request->input('pertemuan_id') : null;

        $paginator = $this->forumService->listTopik($kelasId, (int) $request->user()->id, $perPage, $sortBy, $sortOrder, $pertemuanId);
        $paginator->appends($request->query());

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar topik forum kelas berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'filters' => [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'pertemuan_id' => $pertemuanId,
                ],
            ],
        ]);
    }

    /**
     * Buat topik forum baru (dosen/pengelola kelas).
     */
    public function storeTopik(StoreForumTopikRequest $request, int $kelasId): JsonResponse
    {
        Gate::authorize('create', ForumTopik::class);

        $topik = $this->forumService->createTopik($kelasId, $request->validated(), (int) $request->user()->id);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_forum_topik',
                recordId: $topik->id,
                oldValues: null,
                newValues: $topik->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log buat topik forum: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Topik forum berhasil dibuat.',
            'data' => $topik,
        ], 201);
    }

    /**
     * Hapus topik beserta seluruh pesannya.
     */
    public function destroyTopik(int $topikId): JsonResponse
    {
        $topik = ForumTopik::findOrFail($topikId);
        Gate::authorize('delete', $topik);

        // Snapshot diambil sebelum penghapusan, audit log baru ditulis setelah
        // delete benar-benar berhasil supaya tidak mencatat aksi yang gagal.
        $oldValues = $topik->getOriginal();

        $this->forumService->deleteTopik($topikId);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_forum_topik',
                recordId: $topikId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus topik forum: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Topik forum berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Daftar pesan satu topik (paginated, beserta balasan).
     */
    public function listPost(Request $request, int $topikId): JsonResponse
    {
        Gate::authorize('viewAny', ForumPost::class);

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        [$sortBy, $sortOrder] = $this->forumService->normalisasiSort(
            (string) $request->input('sort_by', 'id'),
            (string) $request->input('sort_order', 'asc'),
            ForumService::SORT_POST
        );

        $paginator = $this->forumService->listPost(
            $topikId,
            (int) $request->user()->id,
            $perPage,
            $sortBy,
            $sortOrder
        );
        $paginator->appends($request->query());

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pesan forum berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'filters' => [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    /**
     * Kirim pesan / balasan (1 level) ke topik.
     */
    public function storePost(StoreForumPostRequest $request, int $topikId): JsonResponse
    {
        Gate::authorize('create', ForumPost::class);

        $post = $this->forumService->createPost($topikId, $request->validated(), (int) $request->user()->id);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_forum_post',
                recordId: $post->id,
                oldValues: null,
                newValues: $post->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log buat post forum: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan forum berhasil dikirim.',
            'data' => $post->load('balasan'),
        ], 201);
    }

    /**
     * Hapus pesan. Penulis pesan sendiri atau pengelola kelas (lihat ForumPostPolicy).
     */
    public function destroyPost(int $postId): JsonResponse
    {
        $post = ForumPost::findOrFail($postId);

        // Otorisasi tingkat resource (pengelola kelas atau penulis pesan sendiri)
        // ditangani ForumPostPolicy; controller tidak melakukan cek ad-hoc.
        Gate::authorize('delete', $post);

        // Snapshot diambil sebelum penghapusan, audit log baru ditulis setelah
        // delete benar-benar berhasil supaya tidak mencatat aksi yang gagal.
        $oldValues = $post->getOriginal();

        $this->forumService->deletePost($postId);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_forum_post',
                recordId: $postId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus post forum: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan forum berhasil dihapus.',
            'data' => null,
        ]);
    }
}
