<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\KoreksiAlokasi;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\AuditLogService;
use App\Services\Sikeu\KoreksiAlokasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class KoreksiAlokasiController extends Controller
{
    public function __construct(private KoreksiAlokasiService $service) {}

    /**
     * GET /api/v1/sikeu/koreksi-alokasi
     */
    public function index(Request $request)
    {
        $this->authorize('sikeu.tagihan.read');
        $perPage = min(100, $request->integer('per_page', 15));
        $query = KoreksiAlokasi::with(['details.fromTagihan', 'details.toTagihan', 'mahasiswa']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('mahasiswa_id')) {
            $query->where('mahasiswa_id', (int) $request->mahasiswa_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_koreksi', 'like', "%{$search}%")
                    ->orWhere('alasan', 'like', "%{$search}%")
                    ->orWhere('referensi', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['id', 'created_at', 'nomor_koreksi', 'status', 'total_nominal_dialihkan'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar koreksi alokasi berhasil dimuat',
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
     * GET /api/v1/sikeu/koreksi-alokasi/konsolidasi?mahasiswa_id=
     */
    public function konsolidasi(Request $request)
    {
        $this->authorize('sikeu.tagihan.read');
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'nullable|integer|exists:siakad_mahasiswa,id',
            'calon_mahasiswa_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi konsolidasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->service->konsolidasi(
            $request->filled('mahasiswa_id') ? (int) $request->mahasiswa_id : null,
            $request->filled('calon_mahasiswa_id') ? (int) $request->calon_mahasiswa_id : null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Konsolidasi tagihan mahasiswa berhasil dimuat',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/sikeu/koreksi-alokasi/simulate
     */
    public function simulate(Request $request)
    {
        $this->authorize('sikeu.tagihan.read');
        $validator = Validator::make($request->all(), [
            'details' => 'required|array|min:1',
            'details.*.from_tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'details.*.to_tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id|different:details.*.from_tagihan_id',
            'details.*.nominal_dialihkan' => 'required|numeric|min:1',
            'details.*.keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi simulasi koreksi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $hasil = $this->service->simulate($request->only('details'));

            return response()->json([
                'status' => 'success',
                'message' => 'Simulasi koreksi berhasil dihitung',
                'data' => $hasil,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung simulasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/koreksi-alokasi/draft
     */
    public function draft(Request $request)
    {
        $this->authorize('sikeu.tagihan.create');
        $validator = Validator::make($request->all(), [
            'alasan' => 'required|string|min:10|max:1000',
            'referensi' => 'nullable|string|max:255',
            'tahun_akademik_id' => 'nullable|integer',
            'semester' => 'nullable|integer|min:1|max:14',
            'buat_tagihan_kekurangan' => 'nullable|boolean',
            'details' => 'required|array|min:1',
            'details.*.from_tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'details.*.to_tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id|different:details.*.from_tagihan_id',
            'details.*.nominal_dialihkan' => 'required|numeric|min:1',
            'details.*.from_detail_tagihan_id' => 'nullable|integer|exists:sikeu_detail_tagihan,id',
            'details.*.to_detail_tagihan_id' => 'nullable|integer|exists:sikeu_detail_tagihan,id',
            'details.*.keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi draft koreksi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $koreksi = $this->service->createDraft((int) auth()->id(), $request->all());

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'create',
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $koreksi->id,
                    oldValues: null,
                    newValues: array_merge($koreksi->getChanges(), ['nomor_koreksi' => $koreksi->nomor_koreksi]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log draft koreksi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Draft koreksi {$koreksi->nomor_koreksi} berhasil dibuat dan menunggu persetujuan.",
                'data' => $koreksi,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'ditutup') ? 403 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat draft koreksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/sikeu/koreksi-alokasi/{id}
     */
    public function show($id)
    {
        $this->authorize('sikeu.tagihan.read');
        $koreksi = KoreksiAlokasi::with([
            'details.fromTagihan',
            'details.toTagihan',
            'mahasiswa.programStudi',
            'tagihanKekurangan',
        ])->findOrFail($id);

        // Sertakan simulasi terkini agar UI bisa menampilkan sisa/kekurangan live.
        try {
            $simulasi = $this->service->simulate([
                'details' => $koreksi->details->map(fn ($d) => [
                    'from_tagihan_id' => $d->from_tagihan_id,
                    'to_tagihan_id' => $d->to_tagihan_id,
                    'nominal_dialihkan' => (float) $d->nominal_dialihkan,
                ])->all(),
            ], $koreksi->id);
        } catch (\Throwable $e) {
            $simulasi = ['error' => $e->getMessage()];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail koreksi berhasil dimuat',
            'data' => array_merge($koreksi->toArray(), ['simulasi_terkini' => $simulasi]),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $this->authorize('sikeu.approval.approve');
        $validator = Validator::make($request->all(), ['catatan' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }
        $koreksi = KoreksiAlokasi::with('details')->findOrFail($id);

        try {
            $oldValuesApprove = $koreksi->getOriginal();
            $hasil = $this->service->approve($koreksi, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'approve',
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $koreksi->id,
                    oldValues: $oldValuesApprove,
                    newValues: array_merge($hasil->getChanges(), ['nomor_koreksi' => $koreksi->nomor_koreksi, 'catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log approve koreksi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Koreksi {$hasil->nomor_koreksi} disetujui. Siap diterapkan.",
                'data' => $hasil,
            ]);
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
        $koreksi = KoreksiAlokasi::with('details')->findOrFail($id);

        try {
            $oldValuesReject = $koreksi->getOriginal();
            $hasil = $this->service->reject($koreksi, (int) auth()->id(), $request->catatan);

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'reject',
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $koreksi->id,
                    oldValues: $oldValuesReject,
                    newValues: array_merge($hasil->getChanges(), ['nomor_koreksi' => $koreksi->nomor_koreksi, 'catatan' => $request->catatan]),
                    request: $request,
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log reject koreksi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Koreksi {$hasil->nomor_koreksi} ditolak.",
                'data' => $hasil,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function apply($id)
    {
        $this->authorize('sikeu.approval.approve');
        $koreksi = KoreksiAlokasi::with('details')->findOrFail($id);

        try {
            $oldValuesApply = $koreksi->getOriginal();
            $hasil = $this->service->apply($koreksi, (int) auth()->id());

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'approve',
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $koreksi->id,
                    oldValues: $oldValuesApply,
                    newValues: array_merge($hasil['koreksi']->getChanges(), [
                        'nomor_koreksi' => $koreksi->nomor_koreksi,
                        'tagihan_kekurangan' => collect($hasil['tagihan_kekurangan'])->pluck('nomor_tagihan')->all(),
                    ]),
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log apply koreksi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => count($hasil['tagihan_kekurangan']) > 0
                    ? "Koreksi {$koreksi->nomor_koreksi} diterapkan. " . count($hasil['tagihan_kekurangan']) . ' tagihan kekurangan otomatis diterbitkan.'
                    : "Koreksi {$koreksi->nomor_koreksi} diterapkan. Seluruh target lunas.",
                'data' => $hasil,
            ]);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'ditutup') ? 403 : 422;

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            \Log::error('Apply koreksi gagal: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Gagal menerapkan koreksi: ' . $e->getMessage()], 500);
        }
    }

    public function cancel($id)
    {
        $this->authorize('sikeu.approval.reject');
        $koreksi = KoreksiAlokasi::with('details')->findOrFail($id);

        try {
            $oldValuesCancel = $koreksi->getOriginal();
            $hasil = $this->service->cancel($koreksi, (int) auth()->id());

            try {
                AuditLogService::record(
                    module: 'SIKEU',
                    action: 'reject',
                    tableName: 'sikeu_koreksi_alokasi',
                    recordId: $koreksi->id,
                    oldValues: $oldValuesCancel,
                    newValues: array_merge($hasil->getChanges(), ['nomor_koreksi' => $koreksi->nomor_koreksi]),
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log cancel koreksi: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Koreksi {$hasil->nomor_koreksi} dibatalkan.",
                'data' => $hasil,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/sikeu/koreksi-alokasi/tagihan/{id}/sisa
     * Helper cepat untuk UI: sisa + tersedia dialihkan satu tagihan.
     */
    public function sisaTagihan($id)
    {
        $this->authorize('sikeu.tagihan.read');
        $tagihan = TagihanMahasiswa::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Sisa tagihan berhasil dimuat',
            'data' => [
                'id' => $tagihan->id,
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'total_bersih' => KoreksiAlokasiService::totalBersih($tagihan),
                'total_bayar' => (float) $tagihan->total_bayar,
                'sisa' => KoreksiAlokasiService::sisa($tagihan),
                'tersedia_dialihkan' => KoreksiAlokasiService::tersediaDialihkan($tagihan),
                'status' => $tagihan->status,
            ],
        ]);
    }
}
