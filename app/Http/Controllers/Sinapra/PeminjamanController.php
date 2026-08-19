<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Services\Sinapra\PeminjamanService;
use App\Http\Requests\Sinapra\ApplyPeminjamanRuanganRequest;
use App\Http\Requests\Sinapra\ApprovePeminjamanRuanganRequest;
use App\Http\Requests\Sinapra\ApplyPeminjamanAsetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PeminjamanController extends Controller
{
    public function __construct(private PeminjamanService $service) {}

    // ── PEMINJAMAN RUANGAN ENDPOINTS ──────────────────────────────────────

    public function indexRuangan(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PeminjamanRuangan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = PeminjamanRuangan::with(['ruangan.gedung', 'user', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('ruangan_id')) {
            $query->where('ruangan_id', $request->ruangan_id);
        }

        if ($request->filled('tanggal')) {
            $query->where('tanggal', $request->tanggal);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keperluan', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $allowedSort = ['created_at', 'tanggal', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar peminjaman ruangan berhasil diambil',
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
                'ruangan_id' => $request->ruangan_id,
                'tanggal' => $request->tanggal,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function applyRuangan(ApplyPeminjamanRuanganRequest $request): JsonResponse
    {
        $this->authorize('create', PeminjamanRuangan::class);

        $peminjaman = $this->service->applyPeminjamanRuangan(
            data: $request->validated(),
            userId: $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan peminjaman ruangan berhasil dikirim',
            'data' => $peminjaman->load('ruangan'),
        ], 201);
    }

    public function showRuangan(PeminjamanRuangan $peminjaman): JsonResponse
    {
        $this->authorize('view', $peminjaman);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail peminjaman ruangan berhasil diambil',
            'data' => $peminjaman->load(['ruangan.gedung', 'user', 'approver']),
        ]);
    }

    public function approveRuangan(ApprovePeminjamanRuanganRequest $request, PeminjamanRuangan $peminjaman): JsonResponse
    {
        $this->authorize('approve', $peminjaman);

        $updated = $this->service->approvePeminjamanRuangan(
            peminjaman: $peminjaman,
            approverId: $request->user()->id,
            isApproved: $request->boolean('is_approved'),
            catatanPenolakan: $request->input('catatan_penolakan')
        );

        return response()->json([
            'status' => 'success',
            'message' => $request->boolean('is_approved') ? 'Peminjaman ruangan berhasil disetujui' : 'Peminjaman ruangan ditolak',
            'data' => $updated->load(['ruangan', 'user', 'approver']),
        ]);
    }

    // ── PEMINJAMAN ASET ENDPOINTS ─────────────────────────────────────────

    public function indexAset(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PeminjamanAset::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = PeminjamanAset::with(['aset.kategori', 'user', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('aset_id')) {
            $query->where('aset_id', $request->aset_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keperluan', 'like', "%{$search}%")
                  ->orWhereHas('aset', fn($a) => $a->where('nama', 'like', "%{$search}%")->orWhere('kode_aset', 'like', "%{$search}%"));
            });
        }

        $allowedSort = ['created_at', 'tanggal_pinjam', 'tanggal_kembali_rencana', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar peminjaman aset berhasil diambil',
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
                'aset_id' => $request->aset_id,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function applyAset(ApplyPeminjamanAsetRequest $request): JsonResponse
    {
        $this->authorize('create', PeminjamanAset::class);

        $peminjaman = $this->service->applyPeminjamanAset(
            data: $request->validated(),
            userId: $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan peminjaman aset berhasil dikirim',
            'data' => $peminjaman->load('aset'),
        ], 201);
    }

    public function showAset(PeminjamanAset $peminjaman): JsonResponse
    {
        $this->authorize('view', $peminjaman);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail peminjaman aset berhasil diambil',
            'data' => $peminjaman->load(['aset.kategori', 'user', 'approver']),
        ]);
    }

    public function approveAset(Request $request, PeminjamanAset $peminjaman): JsonResponse
    {
        $this->authorize('approve', $peminjaman);

        $request->validate(['is_approved' => 'required|boolean']);

        $updated = $this->service->approvePeminjamanAset(
            peminjaman: $peminjaman,
            approverId: $request->user()->id,
            isApproved: $request->boolean('is_approved')
        );

        return response()->json([
            'status' => 'success',
            'message' => $request->boolean('is_approved') ? 'Peminjaman aset berhasil disetujui' : 'Peminjaman aset ditolak',
            'data' => $updated->load(['aset', 'user', 'approver']),
        ]);
    }

    public function kembalikanAset(Request $request, PeminjamanAset $peminjaman): JsonResponse
    {
        $this->authorize('approve', $peminjaman);

        $request->validate([
            'kondisi_kembali' => 'required|in:baik,rusak_ringan,rusak_berat',
        ]);

        $updated = $this->service->prosesPengembalianAset(
            peminjaman: $peminjaman,
            kondisiKembali: $request->kondisi_kembali
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengembalian aset berhasil diproses',
            'data' => $updated->load(['aset', 'user']),
        ]);
    }
}
