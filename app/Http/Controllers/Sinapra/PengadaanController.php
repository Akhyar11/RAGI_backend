<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\PengajuanPengadaan;
use App\Services\Sinapra\PengadaanService;
use App\Http\Requests\Sinapra\PengajuanPengadaanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PengadaanController extends Controller
{
    public function __construct(private PengadaanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PengajuanPengadaan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = PengajuanPengadaan::with(['unitKerja', 'pengaju', 'approver'])->withCount('details');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('unit_kerja_id')) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('alasan_kebutuhan', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'tanggal_pengajuan', 'estimasi_anggaran', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan pengadaan berhasil diambil',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'unit_kerja_id' => $request->unit_kerja_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(PengajuanPengadaanRequest $request): JsonResponse
    {
        $this->authorize('create', PengajuanPengadaan::class);

        $pengajuan = $this->service->createPengajuan(
            data: $request->validated(),
            diajukanOleh: $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pengadaan berhasil dibuat',
            'data' => $pengajuan->load(['unitKerja', 'pengaju', 'details.kategoriAset']),
        ], 201);
    }

    public function show(PengajuanPengadaan $pengadaan): JsonResponse
    {
        $this->authorize('view', $pengadaan);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pengajuan pengadaan berhasil diambil',
            'data' => $pengadaan->load(['unitKerja', 'pengaju', 'approver', 'details.kategoriAset']),
        ]);
    }

    public function updateStatus(Request $request, PengajuanPengadaan $pengadaan): JsonResponse
    {
        $this->authorize('update', $pengadaan);

        $request->validate([
            'status' => 'required|in:draft,diajukan,disetujui,ditolak,proses_pengadaan,selesai',
        ]);

        $updated = $this->service->updateStatusPengadaan(
            pengajuan: $pengadaan,
            approverId: $request->user()->id,
            status: $request->status
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan pengadaan berhasil diperbarui',
            'data' => $updated->load(['unitKerja', 'pengaju', 'approver', 'details']),
        ]);
    }

    public function destroy(PengajuanPengadaan $pengadaan): JsonResponse
    {
        $this->authorize('delete', $pengadaan);

        $this->service->deletePengajuan($pengadaan);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan pengadaan berhasil dihapus',
            'data' => null,
        ]);
    }
}
