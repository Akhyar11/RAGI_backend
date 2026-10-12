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
            'pegawai:id,nama_lengkap,nip,nidn,unit_kerja_id',
            'pegawai.unitKerja:id,nama',
            'fileUploader:id,name,email',
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
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'status' => 'nullable|string|in:terpakai,direservasi,dibatalkan',
            'catatan' => 'nullable|string',
        ]);

        $updated = $this->service->update($id, $request->only(['perihal', 'tujuan', 'pegawai_id', 'status', 'catatan']));

        return response()->json([
            'status' => 'success',
            'message' => 'Data nomor surat berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Upload berkas scan dokumen fisik surat resmi oleh Admin ARSIP.
     */
    public function uploadSurat(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.update') && !$user->hasPermission('arsip.nomor_surat.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengunggah berkas scan nomor surat.',
            ], 403);
        }

        $request->validate([
            'file_surat' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'file_surat.required' => 'Berkas scan surat wajib diunggah.',
            'file_surat.mimes' => 'Format berkas harus berupa PDF atau gambar (JPG, PNG).',
            'file_surat.max' => 'Ukuran berkas scan surat maksimal 10 MB.',
        ]);

        $result = $this->service->uploadFileSurat($id, $request->file('file_surat'), $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas scan dokumen surat berhasil diunggah.',
            'data' => $result,
        ]);
    }

    /**
     * Download / ambil tautan unduh berkas scan dokumen surat resmi.
     */
     public function downloadSurat(Request $request, int $id): JsonResponse
     {
         $user = $request->user();
         if (!$user->hasPermission('arsip.nomor_surat.read') && !$user->isAdmin()) {
             return response()->json([
                 'status' => 'error',
                 'message' => 'Anda tidak memiliki hak akses untuk mengunduh dokumen surat.',
             ], 403);
         }

         $nomor = NomorSurat::findOrFail($id);
         if (empty($nomor->file_surat_path)) {
             return response()->json([
                 'status' => 'error',
                 'message' => 'Berkas scan dokumen surat belum diunggah.',
             ], 404);
         }

         try {
             AuditLogService::record(
                 module: 'ARSIP',
                 action: 'download_file_surat',
                 tableName: 'core_arsip_nomor_surat',
                 recordId: $nomor->id,
                 oldValues: null,
                 newValues: ['downloaded_at' => now()->toIso8601String()]
             );
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log download file surat: ' . $e->getMessage());
         }

         /** @var \App\Services\Storage\FileStorageService $fileStorage */
         $fileStorage = app(\App\Services\Storage\FileStorageService::class);
         $signedUrl = $fileStorage->signedUrl($nomor->file_surat_path);

         return response()->json([
             'status' => 'success',
             'message' => 'Tautan unduh dokumen surat berhasil dibuat.',
             'data' => [
                 'nomor_surat' => $nomor->nomor_surat,
                 'download_url' => $signedUrl,
                 'expires_in_minutes' => 15,
             ],
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
