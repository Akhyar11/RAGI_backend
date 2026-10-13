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
        if (!$user->hasPermission('simpeg.nomor_surat.read') && !$user->hasPermission('arsip.nomor_surat.read') && !$user->hasPermission('simpeg.pegawai.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat daftar nomor surat.',
            ], 403);
        }

        $pegawai = Pegawai::where('user_id', $user->id)->first();

        $perPage = min(100, $request->integer('per_page', 15));
        $filters = $request->only(['search', 'status', 'has_file', 'sort_by', 'sort_order']);

        $canManageAll = $user->hasPermission('simpeg.nomor_surat.update') 
            || $user->hasPermission('simpeg.nomor_surat.delete')
            || $user->hasPermission('arsip.nomor_surat.read');

        if (!$pegawai && !$canManageAll) {
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

        // Ambil data pengajuan nomor surat (request) dari user/pegawai
        $reqQuery = \App\Models\Arsip\RequestNomorSurat::with([
            'nomorSurat' => function ($q) {
                $q->with(['kopSurat', 'fileUploader:id,name,email']);
            },
            'user:id,name,email,username',
            'verifikator:id,name,email',
        ]);

        if (!$canManageAll) {
            $pegId = $pegawai?->id;
            $userId = $user->id;
            $reqQuery->where(function ($q) use ($userId, $pegId) {
                $q->where('user_id', $userId);
                if ($pegId) {
                    $q->orWhere('reference_id', $pegId);
                }
            });
        } elseif ($request->filled('pegawai_id')) {
            $reqQuery->where('reference_id', $request->pegawai_id);
        }

        // Ambil juga nomor surat yang diterbitkan langsung tanpa request
        $directNomorQuery = NomorSurat::with([
            'pembuat:id,name,email,username',
            'pegawai:id,nama_lengkap,nip,nidn,unit_kerja_id',
            'pegawai.unitKerja:id,nama',
            'fileUploader:id,name,email',
            'kopSurat',
        ])->whereNull('request_id');

        if (!$canManageAll) {
            $pegId = $pegawai?->id;
            $userId = $user->id;
            $directNomorQuery->where(function ($q) use ($pegId, $userId) {
                if ($pegId) {
                    $q->where('pegawai_id', $pegId);
                }
                $q->orWhere('created_by', $userId);
            });
        } elseif ($request->filled('pegawai_id')) {
            $directNomorQuery->where('pegawai_id', $request->pegawai_id);
        }

        $allRequests = $reqQuery->orderBy('created_at', 'desc')->get();
        $allDirects = $directNomorQuery->orderBy('created_at', 'desc')->get();

        $combined = collect();

        foreach ($allRequests as $req) {
            if ($req->nomorSurat && $req->nomorSurat->isNotEmpty()) {
                foreach ($req->nomorSurat as $ns) {
                    $nsArr = $ns->toArray();
                    $nsArr['request'] = [
                        'id' => $req->id,
                        'kode_request' => $req->kode_request,
                        'status' => $req->status,
                        'catatan_verifikasi' => $req->catatan_verifikasi,
                        'verified_at' => $req->verified_at?->toIso8601String(),
                        'catatan_pemohon' => $req->catatan_pemohon,
                    ];
                    $nsArr['status_pengajuan'] = $req->status; // 'disetujui'
                    $nsArr['is_request_only'] = false;
                    $combined->push($nsArr);
                }
            } else {
                $combined->push([
                    'id' => null,
                    'nomor_surat' => '-',
                    'nomor_urut' => null,
                    'kode_unit' => $req->kode_unit,
                    'kode_klasifikasi' => $req->kode_klasifikasi,
                    'tanggal_surat' => $req->tanggal_surat?->format('Y-m-d'),
                    'perihal' => $req->perihal,
                    'tujuan' => $req->tujuan,
                    'status' => $req->status === 'ditolak' ? 'dibatalkan' : 'menunggu',
                    'status_pengajuan' => $req->status, // 'menunggu_verifikasi' atau 'ditolak'
                    'module_origin' => $req->module_origin,
                    'request_id' => $req->id,
                    'pegawai_id' => $pegawai?->id,
                    'kop_surat_id' => null,
                    'file_surat_path' => null,
                    'file_surat_url' => null,
                    'has_file' => false,
                    'catatan' => $req->catatan_verifikasi ?? $req->catatan_pemohon,
                    'created_by' => $req->user_id,
                    'created_at' => $req->created_at?->toIso8601String(),
                    'updated_at' => $req->updated_at?->toIso8601String(),
                    'is_request_only' => true,
                    'request' => [
                        'id' => $req->id,
                        'kode_request' => $req->kode_request,
                        'status' => $req->status,
                        'catatan_verifikasi' => $req->catatan_verifikasi,
                        'verified_at' => $req->verified_at?->toIso8601String(),
                        'catatan_pemohon' => $req->catatan_pemohon,
                    ],
                ]);
            }
        }

        foreach ($allDirects as $ns) {
            $nsArr = $ns->toArray();
            $nsArr['request'] = null;
            $nsArr['status_pengajuan'] = 'disetujui';
            $nsArr['is_request_only'] = false;
            $combined->push($nsArr);
        }

        // Filter status nomor/status pengajuan
        if (!empty($filters['status'])) {
            $statusFilter = strtolower($filters['status']);
            $combined = $combined->filter(function ($item) use ($statusFilter) {
                return (isset($item['status']) && strtolower($item['status']) === $statusFilter) ||
                       (isset($item['status_pengajuan']) && strtolower($item['status_pengajuan']) === $statusFilter);
            });
        }

        if (isset($filters['has_file'])) {
            if ($filters['has_file'] === true || $filters['has_file'] === '1' || $filters['has_file'] === 'true') {
                $combined = $combined->filter(fn($item) => !empty($item['has_file']) || !empty($item['file_surat_path']));
            } elseif ($filters['has_file'] === false || $filters['has_file'] === '0' || $filters['has_file'] === 'false') {
                $combined = $combined->filter(fn($item) => empty($item['has_file']) && empty($item['file_surat_path']));
            }
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $combined = $combined->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['nomor_surat'] ?? ''), $search)
                    || str_contains(strtolower($item['perihal'] ?? ''), $search)
                    || str_contains(strtolower($item['tujuan'] ?? ''), $search)
                    || str_contains(strtolower($item['kode_unit'] ?? ''), $search)
                    || str_contains(strtolower($item['kode_klasifikasi'] ?? ''), $search);
            });
        }

        $sortField = in_array($request->sort_by, ['id', 'nomor_urut', 'tanggal_surat', 'created_at'], true) ? $request->sort_by : 'created_at';
        $isAsc = $request->sort_order === 'asc';

        $combined = $combined->sortBy(function ($item) use ($sortField) {
            return $item[$sortField] ?? '';
        }, SORT_REGULAR, !$isAsc)->values();

        $page = max(1, $request->integer('page', 1));
        $total = $combined->count();
        $slice = $combined->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

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

        $canManage = $user->isAdmin() 
            || $user->hasPermission('simpeg.nomor_surat.upload') 
            || $user->hasPermission('arsip.nomor_surat.update') 
            || $user->hasPermission('simpeg.pegawai.manage');
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
        if (!$user->hasPermission('simpeg.nomor_surat.create') && !$user->hasPermission('arsip.request.create') && !$user->hasPermission('arsip.nomor_surat.create') && !$user->isAdmin()) {
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
