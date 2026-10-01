<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\TemplateSuratSpmb;
use App\Http\Requests\Spmb\StoreTemplateSuratRequest;
use App\Http\Requests\Spmb\UpdateTemplateSuratRequest;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TemplateSuratSpmbController extends Controller
{
    public function __construct(
        protected SpmbPendaftaranService $pendaftaranService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $query = TemplateSuratSpmb::with(['jalurMasuk', 'gelombang']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('judul_surat', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis_surat')) {
            $query->where('jenis_surat', $request->jenis_surat);
        }

        if ($request->filled('jalur_masuk_id')) {
            $query->where('jalur_masuk_id', $request->jalur_masuk_id);
        }

        if ($request->filled('gelombang_id')) {
            $query->where('gelombang_id', $request->gelombang_id);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSortColumns = ['kode', 'nama', 'jenis_surat', 'jalur_masuk_id', 'gelombang_id', 'is_active', 'created_at', 'updated_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, $request->integer('per_page', 15));

        $templates = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data template surat berhasil diambil.',
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'last_page' => $templates->lastPage(),
                'from' => $templates->firstItem(),
                'to' => $templates->lastItem(),
                'filters' => [
                    'search' => $request->search,
                    'jenis_surat' => $request->jenis_surat,
                    'jalur_masuk_id' => $request->jalur_masuk_id,
                    'gelombang_id' => $request->gelombang_id,
                    'is_active' => $request->is_active,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTemplateSuratRequest $request): JsonResponse
    {
        $template = $this->pendaftaranService->storeTemplateSurat($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Template surat berhasil dibuat.',
            'data' => $template,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, TemplateSuratSpmb $templateSurat): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $templateSurat->loadMissing(['jalurMasuk', 'gelombang']);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail template surat berhasil diambil.',
            'data' => $templateSurat,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTemplateSuratRequest $request, TemplateSuratSpmb $templateSurat): JsonResponse
    {
        $template = $this->pendaftaranService->updateTemplateSurat($templateSurat, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Template surat berhasil diperbarui.',
            'data' => $template,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, TemplateSuratSpmb $templateSurat): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $this->pendaftaranService->deleteTemplateSurat($templateSurat);

        return response()->json([
            'status' => 'success',
            'message' => 'Template surat berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Preview template surat dalam format PDF dengan data mock
     */
    public function preview(Request $request, TemplateSuratSpmb $templateSurat)
    {
        abort_unless($request->user()?->hasPermission('spmb.manage'), 403, 'Akses ditolak.');

        $pdfContent = $this->pendaftaranService->previewTemplateSuratPdf($templateSurat);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Preview-Template-' . $templateSurat->kode . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
