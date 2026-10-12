<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\PerubahanProdi;
use App\Services\AuditLogService;
use App\Services\Sikeu\PerubahanProdiService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PerubahanProdiController extends Controller
{
    public function __construct(private PerubahanProdiService $service, private FileStorageService $files) {}

    /**
     * GET /api/v1/sikeu/perubahan-prodi
     */
    public function index(Request $request)
    {
        $this->authorize('sikeu.tagihan.read');
        $perPage = min(100, $request->integer('per_page', 15));
        $query = PerubahanProdi::with(['mahasiswa.programStudi']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', (int) $request->mahasiswa_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                    ->orWhere('referensi_surat', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['id', 'created_at', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar perubahan prodi berhasil dimuat',
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
     * GET /api/v1/sikeu/perubahan-prodi/dampak?mahasiswa_id=
     * Preview tagihan aktif yang terdampak sebelum apply.
     */
    public function dampak(Request $request)
    {
        $this->authorize('sikeu.tagihan.read');
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dampak perubahan prodi berhasil dimuat',
            'data' => $this->service->dampak((int) $request->mahasiswa_id),
        ]);
    }

    /**
     * POST /api/v1/sikeu/perubahan-prodi
     */
    public function store(Request $request)
    {
        $this->authorize('sikeu.tagihan.create');
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'new_program_studi_id' => 'required|integer|exists:siakad_program_studi,id',
            'tahun_akademik_id' => 'nullable|integer',
            'semester_efektif' => 'nullable|integer|min:1|max:14',
            'tanggal_efektif' => 'nullable|date',
            'jenis_pendaftaran_lama' => 'nullable|string|max:50',
            'jenis_pendaftaran_baru' => 'nullable|string|max:50',
            'alasan' => 'required|string|min:10|max:1000',
            'referensi_surat' => 'nullable|string|max:255',
            'dokumen_path' => 'nullable|string|max:255',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi pengajuan gagal.', 'errors' => $validator->errors()], 422);
        }

        try {
            $validated = $validator->validated();
            if ($request->hasFile('dokumen')) {
                $validated['dokumen_path'] = $this->files->store($request->file('dokumen'), 'sikeu/perubahan-prodi', private: true);
            }
            unset($validated['dokumen']);
            $item = $this->service->createRequest((int) auth()->id(), $validated);

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'create',
                    tableName: 'sikeu_mahasiswa_perubahan_prodi',
                    recordId: $item->id,
                    oldValues: null,
                    newValues: array_merge($item->getChanges(), ['mahasiswa_id' => $item->mahasiswa_id]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log pengajuan perubahan prodi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan perubahan prodi berhasil dibuat.',
                'data' => $item,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat pengajuan: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $this->authorize('sikeu.tagihan.read');
        $item = PerubahanProdi::with(['mahasiswa.programStudi'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail perubahan prodi berhasil dimuat',
            'data' => array_merge($item->toArray(), [
                'dampak' => $this->service->dampak($item->mahasiswa_id),
            ]),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $this->authorize('sikeu.approval.approve');
        $validator = Validator::make($request->all(), ['catatan' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }
        $item = PerubahanProdi::findOrFail($id);

        try {
            $oldApprove = $item->getOriginal();
            $hasil = $this->service->approve($item, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'approve',
                    tableName: 'sikeu_mahasiswa_perubahan_prodi',
                    recordId: $item->id,
                    oldValues: $oldApprove,
                    newValues: array_merge($hasil->getChanges(), ['catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log approve perubahan prodi: ' . $e->getMessage());
            }

            return response()->json(['status' => 'success', 'message' => 'Pengajuan disetujui.', 'data' => $hasil]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, $id)
    {
        $this->authorize('sikeu.approval.reject');
        $validator = Validator::make($request->all(), ['catatan' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }
        $item = PerubahanProdi::findOrFail($id);

        try {
            $oldReject = $item->getOriginal();
            $hasil = $this->service->reject($item, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'reject',
                    tableName: 'sikeu_mahasiswa_perubahan_prodi',
                    recordId: $item->id,
                    oldValues: $oldReject,
                    newValues: array_merge($hasil->getChanges(), ['catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log reject perubahan prodi: ' . $e->getMessage());
            }

            return response()->json(['status' => 'success', 'message' => 'Pengajuan ditolak.', 'data' => $hasil]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function apply($id)
    {
        $this->authorize('sikeu.approval.approve');
        $item = PerubahanProdi::findOrFail($id);

        try {
            $oldApply = $item->getOriginal();
            $hasil = $this->service->apply($item, (int) auth()->id());

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'approve',
                    tableName: 'sikeu_mahasiswa_perubahan_prodi',
                    recordId: $item->id,
                    oldValues: $oldApply,
                    newValues: array_merge($hasil->getChanges(), ['new_program_studi_id' => $hasil->new_program_studi_id]),
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log apply perubahan prodi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan prodi diterapkan. Penagihan berikutnya mengikuti prodi tujuan.',
                'data' => $hasil,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
