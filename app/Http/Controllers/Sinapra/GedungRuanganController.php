<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\Gedung;
use App\Models\Ruangan;
use App\Services\Sinapra\GedungRuanganService;
use App\Http\Requests\Sinapra\GedungRequest;
use App\Http\Requests\Sinapra\RuanganRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GedungRuanganController extends Controller
{
    public function __construct(private GedungRuanganService $service) {}

    // ── GEDUNG ENDPOINTS ───────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Gedung::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = Gedung::withCount('ruangan');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $allowedSort = ['created_at', 'updated_at', 'kode', 'nama', 'jumlah_lantai'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar gedung berhasil diambil',
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
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(GedungRequest $request): JsonResponse
    {
        $this->authorize('create', Gedung::class);

        $gedung = $this->service->createGedung($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data gedung berhasil ditambahkan',
            'data' => $gedung,
        ], 201);
    }

    public function show(Gedung $gedung): JsonResponse
    {
        $this->authorize('view', $gedung);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail gedung berhasil diambil',
            'data' => $gedung->load('ruangan'),
        ]);
    }

    public function update(GedungRequest $request, Gedung $gedung): JsonResponse
    {
        $this->authorize('update', $gedung);

        $updated = $this->service->updateGedung($gedung, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data gedung berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function destroy(Gedung $gedung): JsonResponse
    {
        $this->authorize('delete', $gedung);

        $this->service->deleteGedung($gedung);

        return response()->json([
            'status' => 'success',
            'message' => 'Data gedung berhasil dihapus',
            'data' => null,
        ]);
    }

    // ── RUANGAN ENDPOINTS ──────────────────────────────────────────────────

    public function indexRuangan(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ruangan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = Ruangan::with('gedung');

        if ($request->filled('gedung_id')) {
            $query->where('gedung_id', $request->gedung_id);
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'kode', 'nama', 'kapasitas', 'lantai'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar ruangan berhasil diambil',
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
                'gedung_id' => $request->gedung_id,
                'tipe' => $request->tipe,
                'status' => $request->status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function storeRuangan(RuanganRequest $request): JsonResponse
    {
        $this->authorize('create', Ruangan::class);

        $ruangan = $this->service->createRuangan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data ruangan berhasil ditambahkan',
            'data' => $ruangan->load('gedung'),
        ], 201);
    }

    public function showRuangan(Ruangan $ruangan): JsonResponse
    {
        $this->authorize('view', $ruangan);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail ruangan berhasil diambil',
            'data' => $ruangan->load(['gedung', 'aset']),
        ]);
    }

    public function updateRuangan(RuanganRequest $request, Ruangan $ruangan): JsonResponse
    {
        $this->authorize('update', $ruangan);

        $updated = $this->service->updateRuangan($ruangan, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data ruangan berhasil diperbarui',
            'data' => $updated->load('gedung'),
        ]);
    }

    public function destroyRuangan(Ruangan $ruangan): JsonResponse
    {
        $this->authorize('delete', $ruangan);

        $this->service->deleteRuangan($ruangan);

        return response()->json([
            'status' => 'success',
            'message' => 'Data ruangan berhasil dihapus',
            'data' => null,
        ]);
    }

    public function checkKetersediaanRuangan(Request $request): JsonResponse
    {
        $request->validate([
            'ruangan_id' => 'required|exists:ruangan,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
        ]);

        $available = $this->service->checkRuanganKetersediaan(
            ruanganId: $request->ruangan_id,
            tanggal: $request->tanggal,
            jamMulai: $request->jam_mulai,
            jamSelesai: $request->jam_selesai
        );

        return response()->json([
            'status' => 'success',
            'message' => $available ? 'Ruangan tersedia untuk dipinjam' : 'Ruangan tidak tersedia pada jam tersebut',
            'data' => [
                'is_available' => $available,
            ],
        ]);
    }
}
