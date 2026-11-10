<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\DecisionNilaiKoreksiRequest;
use App\Http\Requests\Siakad\KoreksiNilaiRequest;
use App\Models\Siakad\KoreksiNilaiMahasiswa;
use App\Services\Siakad\NilaiKoreksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NilaiKoreksiController extends Controller
{
    public function __construct(protected NilaiKoreksiService $nilaiKoreksiService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (
            !$user
            || (!$user->hasPermission('siakad.nilai.koreksi.create')
                && !$this->nilaiKoreksiService->canReview($user))
        ) {
            abort(403, 'Anda tidak memiliki izin untuk melihat pengajuan koreksi nilai.');
        }

        $status = $request->query('status');
        if ($status !== null && !in_array($status, [
            KoreksiNilaiMahasiswa::STATUS_MENUNGGU,
            KoreksiNilaiMahasiswa::STATUS_DISETUJUI,
            KoreksiNilaiMahasiswa::STATUS_DITOLAK,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status koreksi nilai tidak valid.',
            ]);
        }

        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'status', 'diputuskan_at'];
        $sortBy = $request->query('sort_by', 'created_at');
        $sortBy = is_string($sortBy) && in_array($sortBy, $allowedSortColumns, true)
            ? $sortBy
            : 'created_at';
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $search = trim((string) $request->query('search', ''));

        $paginator = $this->nilaiKoreksiService->paginate(
            $user,
            min(100, max(1, $request->integer('per_page', 15))),
            $status,
            $search,
            $sortBy,
            $sortOrder
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan koreksi nilai berhasil diambil.',
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
                    'status' => $status,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    public function store(KoreksiNilaiRequest $request): JsonResponse
    {
        $correction = $this->nilaiKoreksiService->submit(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan koreksi nilai berhasil dikirim untuk ditinjau.',
            'data' => $correction,
        ], 201);
    }

    public function approve(DecisionNilaiKoreksiRequest $request, int $id): JsonResponse
    {
        $correction = KoreksiNilaiMahasiswa::findOrFail($id);
        $this->authorize('review', $correction);
        $correction = $this->nilaiKoreksiService->decide(
            $request->user(),
            $id,
            true,
            $request->validated('catatan_reviewer')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Koreksi nilai disetujui dan nilai terkait berhasil disinkronkan.',
            'data' => $correction,
        ]);
    }

    public function reject(DecisionNilaiKoreksiRequest $request, int $id): JsonResponse
    {
        $correction = KoreksiNilaiMahasiswa::findOrFail($id);
        $this->authorize('review', $correction);
        $correction = $this->nilaiKoreksiService->decide(
            $request->user(),
            $id,
            false,
            $request->validated('catatan_reviewer')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan koreksi nilai ditolak.',
            'data' => $correction,
        ]);
    }
}
