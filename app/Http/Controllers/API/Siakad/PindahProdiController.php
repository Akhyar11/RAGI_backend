<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\PindahProdi;
use App\Services\AuditLogService;
use App\Services\Siakad\PindahProdiService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PindahProdiController extends Controller
{
    public function __construct(private PindahProdiService $service, private FileStorageService $files) {}

    /**
     * GET /api/v1/siakad/pindah-prodi
     */
    public function index(Request $request)
    {
        $this->authorize('siakad.mahasiswa.read');
        $perPage = min(100, $request->integer('per_page', 15));
        $query = PindahProdi::with(['mahasiswa.programStudi', 'prodiLama', 'prodiBaru']);

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
                    ->orWhere('referensi_surat', 'like', "%{$search}%")
                    ->orWhereHas('mahasiswa', fn ($mq) => $mq->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%"));
            });
        }

        $allowedSort = ['id', 'created_at', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pindah prodi berhasil dimuat',
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
     * GET /api/v1/siakad/pindah-prodi/dampak?mahasiswa_id=
     * Pratinjau tunggakan SIKEU untuk bahan sign-off keuangan.
     */
    public function dampak(Request $request)
    {
        $this->authorize('siakad.mahasiswa.read');
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $tagihanAktif = \App\Models\Sikeu\TagihanMahasiswa::where('mahasiswa_id', (int) $request->mahasiswa_id)
            ->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi', 'pending_approval'])
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'semester' => $t->semester,
                'total_tagihan' => (float) $t->total_tagihan,
                'total_bayar' => (float) $t->total_bayar,
                'sisa' => max(0, (float) $t->total_tagihan + (float) ($t->total_denda ?? 0) - (float) $t->total_potongan - (float) $t->total_bayar),
                'status' => $t->status,
            ])->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Dampak tunggakan berhasil dimuat',
            'data' => [
                'tagihan_aktif' => $tagihanAktif,
                'total_sisa_aktif' => array_sum(array_column($tagihanAktif, 'sisa')),
                'layak_tanpa_catatan' => empty($tagihanAktif),
            ],
        ]);
    }

    /**
     * POST /api/v1/siakad/pindah-prodi
     */
    public function store(Request $request)
    {
        $this->authorize('siakad.mahasiswa.manage');
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'new_program_studi_id' => 'required|integer|exists:siakad_program_studi,id',
            'tahun_akademik_id' => 'nullable|integer|exists:siakad_tahun_akademik,id',
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
            // Dokumen SK privat: simpan via FileStorageService (disk privat),
            // path relatif yang tersimpan diakses via Signed URL.
            if ($request->hasFile('dokumen')) {
                $validated['dokumen_path'] = $this->files->store($request->file('dokumen'), 'siakad/pindah-prodi', private: true);
            }
            unset($validated['dokumen']);
            $item = $this->service->createRequest((int) auth()->id(), $validated);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'create',
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $item->id,
                    oldValues: null,
                    newValues: array_merge($item->getChanges(), ['mahasiswa_id' => $item->mahasiswa_id]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log pengajuan pindah prodi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pindah prodi berhasil dibuat. Menunggu sign-off keuangan.',
                'data' => $item,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat pengajuan: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $this->authorize('siakad.mahasiswa.read');
        $item = PindahProdi::with(['mahasiswa.programStudi', 'prodiLama', 'prodiBaru'])->findOrFail($id);

        return response()->json(['status' => 'success', 'message' => 'Detail pindah prodi berhasil dimuat', 'data' => $item]);
    }

    /**
     * POST /api/v1/siakad/pindah-prodi/{id}/cek-keuangan
     * Sign-off keuangan (hasil: layak | ada_tunggakan).
     */
    public function cekKeuangan(Request $request, $id)
    {
        // Sign-off boleh dilakukan pemegang alur SIAKAD atau approval keuangan.
        $bolehCekKeuangan = $request->user()->can('siakad.mahasiswa.manage')
            || $request->user()->can('sikeu.approval.approve');
        abort_unless($bolehCekKeuangan, 403, 'Anda tidak memiliki izin untuk melakukan cek keuangan pindah prodi.');
        $validator = Validator::make($request->all(), [
            'hasil' => ['required', Rule::in(PindahProdi::HASIL_CEK_KEUANGAN)],
            'catatan' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $item = PindahProdi::findOrFail($id);

        try {
            $oldCek = $item->getOriginal();
            $hasil = $this->service->cekKeuangan($item, (int) auth()->id(), $request->hasil, $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $item->id,
                    oldValues: $oldCek,
                    newValues: array_merge($hasil->getChanges(), ['hasil_cek_keuangan' => $request->hasil]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log cek keuangan pindah prodi: ' . $e->getMessage());
            }

            return response()->json(['status' => 'success', 'message' => 'Hasil cek keuangan tersimpan.', 'data' => $hasil]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, $id)
    {
        $this->authorize('siakad.mahasiswa.manage');
        $validator = Validator::make($request->all(), ['catatan' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }
        $item = PindahProdi::findOrFail($id);

        try {
            $oldApprove = $item->getOriginal();
            $hasil = $this->service->approve($item, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'approve',
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $item->id,
                    oldValues: $oldApprove,
                    newValues: array_merge($hasil->getChanges(), ['catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log approve pindah prodi: ' . $e->getMessage());
            }

            return response()->json(['status' => 'success', 'message' => 'Pengajuan pindah prodi disetujui.', 'data' => $hasil]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, $id)
    {
        $this->authorize('siakad.mahasiswa.manage');
        $validator = Validator::make($request->all(), ['catatan' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }
        $item = PindahProdi::findOrFail($id);

        try {
            $oldReject = $item->getOriginal();
            $hasil = $this->service->reject($item, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'reject',
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $item->id,
                    oldValues: $oldReject,
                    newValues: array_merge($hasil->getChanges(), ['catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log reject pindah prodi: ' . $e->getMessage());
            }

            return response()->json(['status' => 'success', 'message' => 'Pengajuan pindah prodi ditolak.', 'data' => $hasil]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/siakad/pindah-prodi/{id}/apply
     * Mutasi prodi + dispatch event ke SIKEU (queued).
     */
    public function apply($id)
    {
        $this->authorize('siakad.mahasiswa.manage');
        $item = PindahProdi::findOrFail($id);

        try {
            $oldApply = $item->getOriginal();
            $hasil = $this->service->apply($item, (int) auth()->id());

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'approve',
                    tableName: 'siakad_mahasiswa_pindah_prodi',
                    recordId: $item->id,
                    oldValues: $oldApply,
                    newValues: array_merge($hasil->getChanges(), ['new_program_studi_id' => $hasil->new_program_studi_id]),
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log apply pindah prodi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pindah prodi diterapkan. Konteks penagihan SIKEU dicatat otomatis.',
                'data' => $hasil,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
