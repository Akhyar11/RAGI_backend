<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\ApprovePengajuanKasRequest;
use App\Http\Requests\Sikeu\RejectPengajuanKasRequest;
use App\Http\Requests\Sikeu\StorePengajuanKasRequest;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Services\Sikeu\PengajuanKasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengajuanKasController extends Controller
{
    public function __construct(
        protected PengajuanKasService $pengajuanKasService
    ) {}

    /**
     * Get all Pengajuan Kas
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $query = PengajuanPencairanKas::with(['unitKas', 'pemohon', 'suratTugas.pegawai']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_pengajuan', 'like', "%{$search}%")
                  ->orWhere('judul_pengajuan', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $allowedStatus = ['pending_keuangan', 'dicairkan', 'ditolak'];
        if ($request->filled('status') && in_array($request->status, $allowedStatus, true)) {
            $query->where('status', $request->status);
        }

        $allowedSort = ['created_at', 'nomor_pengajuan', 'nominal_diajukan', 'nominal_disetujui', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengajuan kas berhasil diambil',
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
                'search' => (string) $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Store new Pengajuan Kas
     */
    public function store(StorePengajuanKasRequest $request): JsonResponse
    {
        $pengajuan = $this->pengajuanKasService->create(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan berhasil dibuat',
            'data' => $pengajuan,
        ], 201);
    }

    /**
     * Approve Pengajuan (Cairkan Dana oleh Keuangan/Kabag)
     */
    public function approve(ApprovePengajuanKasRequest $request, $id): JsonResponse
    {
        $pengajuan = PengajuanPencairanKas::findOrFail($id);

        $updated = $this->pengajuanKasService->approve(
            $pengajuan,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Dana berhasil dicairkan dan pengeluaran kas telah dicatat.',
            'data' => $updated,
        ], 200);
    }

    /**
     * Tolak Pengajuan Pencairan Kas
     */
    public function reject(RejectPengajuanKasRequest $request, $id): JsonResponse
    {
        $pengajuan = PengajuanPencairanKas::findOrFail($id);

        $updated = $this->pengajuanKasService->reject(
            $pengajuan,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pencairan kas berhasil ditolak.',
            'data' => $updated,
        ], 200);
    }

    /**
     * GET /api/v1/sikeu/pengajuan-kas/master/status
     * Return distinct status values from pengajuan_pencairan_kas.
     */
    public function getMasterStatus()
    {
        $statuses = PengajuanPencairanKas::select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn($s) => [
                'value' => $s,
                'label' => ucwords(str_replace('_', ' ', $s)),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $statuses,
        ]);
    }
}
