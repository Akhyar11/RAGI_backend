<?php

namespace App\Http\Controllers\Arsip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Arsip\GenerateNomorSuratRequest;
use App\Models\Arsip\NomorSurat;
use App\Services\Arsip\NomorSuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NomorSuratController extends Controller
{
    public function __construct(
        protected NomorSuratService $service
    ) {}

    /**
     * Menampilkan daftar nomor surat resmi dengan filter & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat daftar nomor surat.',
            ], 403);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'tahun', 'kode_unit', 'kode_klasifikasi', 'status', 'module_origin', 'sort_by', 'sort_order']);
        $paginated = $this->service->getPaginated($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar nomor surat berhasil diambil',
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
                'tahun' => $request->tahun,
                'kode_unit' => $request->kode_unit,
                'kode_klasifikasi' => $request->kode_klasifikasi,
                'status' => $request->status,
                'module_origin' => $request->module_origin,
                'sort_by' => $filters['sort_by'] ?? 'created_at',
                'sort_order' => $filters['sort_order'] ?? 'desc',
            ],
        ]);
    }

    /**
     * Generate nomor surat baru (satuan atau bulk).
     */
    public function store(GenerateNomorSuratRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menerbitkan nomor surat.',
            ], 403);
        }

        $validated = $request->validated();
        $mode = $validated['mode'] ?? (($validated['jumlah_nomor'] ?? 1) > 1 ? 'bulk' : 'satuan');

        if ($mode === 'bulk' && ($validated['jumlah_nomor'] ?? 1) > 1) {
            $results = $this->service->generateBulk($validated, $user->id);
            return response()->json([
                'status' => 'success',
                'message' => "Berhasil menerbitkan {$results->count()} nomor surat sekaligus.",
                'data' => $results,
            ], 201);
        }

        $result = $this->service->generateSatuan($validated, $user->id);
        return response()->json([
            'status' => 'success',
            'message' => 'Nomor surat resmi berhasil diterbitkan.',
            'data' => $result,
        ], 201);
    }

    /**
     * Menampilkan rincian nomor surat tertentu.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.nomor_surat.read') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian nomor surat.',
            ], 403);
        }

        $nomor = NomorSurat::with([
            'pembuat:id,name,email,username',
            'kopSurat',
            'request.user:id,name,email',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian nomor surat berhasil dimuat.',
            'data' => $nomor,
        ]);
    }

    /**
     * Memperbarui informasi perihal / catatan nomor surat.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengubah nomor surat.',
            ], 403);
        }

        $request->validate([
            'perihal' => 'sometimes|required|string|max:255',
            'tujuan' => 'nullable|string|max:200',
            'status' => 'nullable|string|in:terpakai,direservasi,dibatalkan',
            'catatan' => 'nullable|string',
        ]);

        $updated = $this->service->update($id, $request->only(['perihal', 'tujuan', 'status', 'catatan']));

        return response()->json([
            'status' => 'success',
            'message' => 'Data nomor surat berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Membatalkan nomor surat.
     */
    public function batalkan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.update') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk membatalkan nomor surat.',
            ], 403);
        }

        $request->validate([
            'alasan' => 'required|string|max:255',
        ]);

        $result = $this->service->batalkan($id, $request->input('alasan'));

        return response()->json([
            'status' => 'success',
            'message' => 'Nomor surat berhasil dibatalkan.',
            'data' => $result,
        ]);
    }
}
