<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Arsip\NomorSurat;
use App\Models\Simpeg\Pegawai;
use App\Services\Arsip\NomorSuratService;
use App\Services\Arsip\RequestNomorSuratService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimpegNomorSuratController extends Controller
{
    public function __construct(
        protected NomorSuratService $nomorSuratService,
        protected RequestNomorSuratService $requestNomorSuratService,
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Mengambil daftar nomor surat resmi yang terikat ke pegawai login (Portal Mandiri).
     */
    public function myLetters(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.nomor_surat.read') && !$user->hasPermission('simpeg.pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat daftar nomor surat.',
            ], 403);
        }

        $pegawai = Pegawai::where('user_id', $user->id)->first();

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'status', 'has_file', 'sort_by', 'sort_order']);

        if (!$pegawai && !$user->isAdmin()) {
            $emptyPaginated = NomorSurat::whereRaw('1 = 0')->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Data nomor surat pegawai kosong.',
                'data' => $emptyPaginated->items(),
                'meta' => [
                    'current_page' => $emptyPaginated->currentPage(),
                    'per_page' => $emptyPaginated->perPage(),
                    'total' => $emptyPaginated->total(),
                    'last_page' => $emptyPaginated->lastPage(),
                    'from' => $emptyPaginated->firstItem(),
                    'to' => $emptyPaginated->lastItem(),
                ],
            ]);
        }

        // Jika bukan admin, kunci filter hanya untuk pegawai tersebut atau nomor surat yang dibuat oleh user
        $query = NomorSurat::with([
            'pembuat:id,name,email,username',
            'pegawai:id,nama_lengkap,nip,nidn,unit_kerja_id',
            'pegawai.unitKerja:id,nama',
            'fileUploader:id,name,email',
            'kopSurat',
        ]);

        if (!$user->isAdmin()) {
            $pegId = $pegawai?->id;
            $userId = $user->id;
            $query->where(function ($q) use ($pegId, $userId) {
                if ($pegId) {
                    $q->where('pegawai_id', $pegId);
                }
                $q->orWhere('created_by', $userId)
                  ->orWhereHas('request', function ($rq) use ($userId, $pegId) {
                      $rq->where('user_id', $userId);
                      if ($pegId) {
                          $rq->orWhere('reference_id', $pegId);
                      }
                  });
            });
        } elseif ($request->filled('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['has_file'])) {
            if ($filters['has_file'] === true || $filters['has_file'] === '1' || $filters['has_file'] === 'true') {
                $query->whereNotNull('file_surat_path');
            } elseif ($filters['has_file'] === false || $filters['has_file'] === '0' || $filters['has_file'] === 'false') {
                $query->whereNull('file_surat_path');
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['id', 'nomor_urut', 'tanggal_surat', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar nomor surat pegawai berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Pegawai mengunggah berkas scan surat yang menjadi tanggung jawabnya.
     */
    public function uploadScan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $nomor = NomorSurat::findOrFail($id);
        $pegawai = Pegawai::where('user_id', $user->id)->first();

        $canManage = $user->isAdmin() || $user->hasPermission('arsip.nomor_surat.update') || $user->hasPermission('simpeg.pegawai.manage');
        $isOwner = ($pegawai && $nomor->pegawai_id === $pegawai->id) 
            || ($nomor->created_by === $user->id)
            || ($nomor->request && $nomor->request->user_id === $user->id)
            || ($pegawai && $nomor->request && $nomor->request->reference_id === $pegawai->id);

        if (!$canManage && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengunggah dokumen pada nomor surat ini.',
            ], 403);
        }

        $request->validate([
            'file_surat' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'file_surat.required' => 'Berkas scan dokumen surat wajib diunggah.',
            'file_surat.mimes' => 'Format berkas harus berupa PDF atau gambar (JPG, PNG).',
            'file_surat.max' => 'Ukuran berkas scan surat maksimal 10 MB.',
        ]);

        $result = $this->nomorSuratService->uploadFileSurat($id, $request->file('file_surat'), $user->id, 'SIMPEG');

        return response()->json([
            'status' => 'success',
            'message' => 'Berkas scan dokumen nomor surat berhasil disimpan dan terarsip.',
            'data' => $result,
        ]);
    }

    /**
     * Download / ambil tautan unduh berkas dokumen surat resmi dari portal SIMPEG.
     */
    public function downloadScan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $nomor = NomorSurat::findOrFail($id);
        $pegawai = Pegawai::where('user_id', $user->id)->first();

        $canManage = $user->isAdmin() || $user->hasPermission('arsip.nomor_surat.read') || $user->hasPermission('simpeg.pegawai.manage');
        $isOwner = ($pegawai && $nomor->pegawai_id === $pegawai->id) 
            || ($nomor->created_by === $user->id)
            || ($nomor->request && $nomor->request->user_id === $user->id)
            || ($pegawai && $nomor->request && $nomor->request->reference_id === $pegawai->id);

        if (!$canManage && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Dokumen ini hanya dapat diakses oleh pihak bersangkutan atau staf SDM/Arsip.',
            ], 403);
        }

        if (empty($nomor->file_surat_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas scan dokumen surat belum diunggah.',
            ], 404);
        }

        try {
            \App\Services\AuditLogService::record(
                module: 'SIMPEG',
                action: 'download_file_surat',
                tableName: 'core_arsip_nomor_surat',
                recordId: $nomor->id,
                oldValues: null,
                newValues: ['downloaded_at' => now()->toIso8601String()]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log download file surat: ' . $e->getMessage());
        }

        $signedUrl = $this->fileStorage->signedUrl($nomor->file_surat_path);

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
     * Pegawai mengajukan request nomor surat baru ke Tim ARSIP dari modul SIMPEG.
     */
    public function submitRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.request.create') && !$user->hasPermission('arsip.nomor_surat.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengajukan permohonan nomor surat.',
            ], 403);
        }

        $pegawai = Pegawai::with('unitKerja')->where('user_id', $user->id)->first();

        if (!$pegawai && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda tidak terhubung dengan data profil pegawai aktif.',
            ], 403);
        }

        $validated = $request->validate([
            'perihal' => 'required|string|max:255',
            'tujuan' => 'nullable|string|max:200',
            'tanggal_surat' => 'required|date',
            'kode_unit' => 'nullable|string|max:50',
            'kode_klasifikasi' => 'nullable|string|max:50',
            'jumlah_nomor' => 'nullable|integer|min:1|max:50',
            'catatan_pemohon' => 'nullable|string',
            'lampiran' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ], [
            'perihal.required' => 'Perihal surat wajib diisi.',
            'tanggal_surat.required' => 'Tanggal surat yang diajukan wajib diisi.',
            'jumlah_nomor.min' => 'Jumlah nomor surat minimal 1.',
            'jumlah_nomor.max' => 'Pengajuan nomor surat mandiri maksimal 50 per permohonan.',
            'lampiran.file' => 'Berkas lampiran harus berupa dokumen atau gambar yang valid.',
            'lampiran.mimes' => 'Format berkas lampiran harus berupa PDF, DOC, DOCX, JPG, JPEG, atau PNG.',
            'lampiran.max' => 'Ukuran berkas lampiran maksimal 10 MB.',
        ]);

        $unitKode = !empty($validated['kode_unit'])
            ? strtoupper(trim($validated['kode_unit']))
            : ($pegawai?->unitKerja?->kode ?? null);

        if (empty($unitKode)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil pegawai belum memiliki unit kerja yang terdaftar. Hubungi administrator kepegawaian.',
                'errors' => [
                    'kode_unit' => ['Unit kerja pemohon tidak ditemukan pada data pegawai aktif.'],
                ],
            ], 422);
        }

        $klasifikasiKode = !empty($validated['kode_klasifikasi'])
            ? strtoupper(trim($validated['kode_klasifikasi']))
            : null;

        $payload = array_merge($validated, [
            'module_origin' => \App\Models\Arsip\RequestNomorSurat::MODULE_ORIGIN_SIMPEG,
            'kode_unit' => $unitKode,
            'kode_klasifikasi' => $klasifikasiKode,
            'reference_type' => Pegawai::class,
            'reference_id' => $pegawai?->id,
        ]);

        $lampiranFile = $request->hasFile('lampiran') && $request->file('lampiran')->isValid()
            ? $request->file('lampiran')
            : null;

        $created = $this->requestNomorSuratService->createRequest(
            $payload,
            $user->id,
            $lampiranFile
        );

        try {
            \App\Services\AuditLogService::record(
                module: 'SIMPEG',
                action: 'submit_request_nomor_surat',
                tableName: 'core_arsip_request_nomor_surat',
                recordId: $created->id,
                oldValues: null,
                newValues: $created->toArray()
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log pengajuan nomor surat: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan nomor surat berhasil diajukan ke Tim Arsip.',
            'data' => $created,
        ], 201);
    }
}
