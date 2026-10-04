<?php

namespace App\Http\Controllers\Arsip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Arsip\ApplyRequestNomorSuratRequest;
use App\Http\Requests\Arsip\VerifyRequestNomorSuratRequest;
use App\Models\Arsip\RequestNomorSurat;
use App\Services\Arsip\RequestNomorSuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestNomorSuratController extends Controller
{
    public function __construct(
        protected RequestNomorSuratService $service
    ) {}

    /**
     * Menampilkan daftar permohonan request nomor surat.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canVerify = $user->hasPermission('arsip.request.approve') || $user->isAdmin();

        // Jika bukan admin / verifikator, batasi hanya melihat request miliknya sendiri
        $onlyUserId = $canVerify ? null : $user->id;

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'status', 'module_origin']);
        $paginated = $this->service->getPaginated($filters, $perPage, $onlyUserId);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar permohonan nomor surat berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'module_origin' => $request->module_origin,
            ],
        ]);
    }

    /**
     * Mengajukan request permohonan nomor surat dari modul lain.
     */
    public function store(ApplyRequestNomorSuratRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.request.create') && !$user->hasPermission('arsip.nomor_surat.create') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengajukan permohonan nomor surat.',
            ], 403);
        }

        $created = $this->service->createRequest(
            $request->validated(),
            $user->id,
            $request->file('lampiran')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan nomor surat berhasil diajukan dan sedang menunggu verifikasi admin arsip.',
            'data' => $created,
        ], 201);
    }

    /**
     * Menampilkan detail permohonan request nomor surat.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $req = RequestNomorSurat::with([
            'user:id,name,email,username',
            'verifikator:id,name,email',
            'nomorSurat.kopSurat',
        ])->findOrFail($id);

        if (!$user || (!$user->hasPermission('arsip.request.read') && !$user->isAdmin() && $req->user_id !== $user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat permohonan nomor surat ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail permohonan nomor surat berhasil dimuat.',
            'data' => $req,
        ]);
    }

    /**
     * Memverifikasi (menyetujui / menolak) request nomor surat oleh admin arsip.
     */
    public function verify(VerifyRequestNomorSuratRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.request.approve') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memverifikasi permohonan nomor surat.',
            ], 403);
        }

        $validated = $request->validated();
        $result = $this->service->verifyRequest(
            $id,
            $validated['action'],
            $validated['catatan'] ?? null,
            $user->id,
            $validated['kode_klasifikasi'] ?? null,
            $validated['kode_unit'] ?? null,
            $validated['perihal'] ?? null,
            $validated['tujuan'] ?? null
        );

        $actionText = in_array($validated['action'], ['setujui', 'approve'], true) ? 'disetujui' : 'ditolak';

        return response()->json([
            'status' => 'success',
            'message' => "Permohonan nomor surat berhasil {$actionText}.",
            'data' => $result,
        ]);
    }
}
