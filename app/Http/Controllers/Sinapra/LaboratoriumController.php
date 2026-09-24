<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sinapra\AlatKalibrasiRequest;
use App\Http\Requests\Sinapra\ApproveBebasTanggunganRequest;
use App\Http\Requests\Sinapra\BebasTanggunganRequest;
use App\Http\Requests\Sinapra\LabBhpRequest;
use App\Http\Requests\Sinapra\LabBhpTransaksiRequest;
use App\Models\AlatKalibrasi;
use App\Models\BebasTanggungan;
use App\Models\LabBhp;
use App\Services\Sinapra\LaboratoriumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LaboratoriumController extends Controller
{
    public function __construct(
        protected LaboratoriumService $laboratoriumService
    ) {}

    // ─────────────────────────────────────────────────────────────
    // 1. ENDPOINTS: BAHAN HABIS PAKAI (BHP LAB)
    // ─────────────────────────────────────────────────────────────

    public function indexBhp(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LabBhp::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = $this->laboratoriumService->getScopedBhpQuery($request->user())
            ->with(['ruangan.gedung', 'kategoriBhp', 'satuanData']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_bhp', 'like', "%{$search}%")
                    ->orWhere('kode_bhp', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhereHas('kategoriBhp', fn($kq) => $kq->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('ruangan_id')) {
            $query->where('ruangan_id', $request->integer('ruangan_id'));
        }

        if ($request->filled('kategori_bhp_id')) {
            $query->where('kategori_bhp_id', $request->integer('kategori_bhp_id'));
        }

        if ($request->filled('satuan_id')) {
            $query->where('satuan_id', $request->integer('satuan_id'));
        }

        if ($request->filled('kategori')) {
            $query->where(function ($q) use ($request) {
                $q->where('kategori', $request->kategori)
                  ->orWhereHas('kategoriBhp', fn($kq) => $kq->where('kode', $request->kategori));
            });
        }

        $allowedSort = ['created_at', 'nama_bhp', 'kode_bhp', 'stok_saat_ini', 'stok_minimum'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar BHP laboratorium berhasil diambil',
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
                'ruangan_id' => $request->ruangan_id,
                'kategori_bhp_id' => $request->kategori_bhp_id,
                'satuan_id' => $request->satuan_id,
                'kategori' => $request->kategori,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function storeBhp(LabBhpRequest $request): JsonResponse
    {
        Gate::authorize('create', LabBhp::class);

        $bhp = LabBhp::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data BHP lab berhasil ditambahkan',
            'data' => $bhp->load(['ruangan.gedung', 'kategoriBhp', 'satuanData']),
        ], 201);
    }

    public function showBhp(LabBhp $labBhp): JsonResponse
    {
        Gate::authorize('view', $labBhp);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail BHP lab berhasil diambil',
            'data' => $labBhp->load(['ruangan.gedung', 'kategoriBhp', 'satuanData', 'transaksi.user']),
        ]);
    }

    public function updateBhp(LabBhpRequest $request, LabBhp $labBhp): JsonResponse
    {
        Gate::authorize('update', $labBhp);

        $labBhp->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data BHP lab berhasil diperbarui',
            'data' => $labBhp->fresh(['ruangan.gedung', 'kategoriBhp', 'satuanData']),
        ]);
    }

    public function destroyBhp(LabBhp $labBhp): JsonResponse
    {
        Gate::authorize('delete', $labBhp);

        $labBhp->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data BHP lab berhasil dihapus',
            'data' => null,
        ]);
    }

    public function transaksiBhp(LabBhpTransaksiRequest $request, LabBhp $labBhp): JsonResponse
    {
        Gate::authorize('manageStock', $labBhp);

        $transaksi = $this->laboratoriumService->catatTransaksiBhp(
            $labBhp,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi mutasi BHP lab berhasil dicatat',
            'data' => [
                'transaksi' => $transaksi,
                'stok_terkini' => $labBhp->fresh()->stok_saat_ini,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. ENDPOINTS: SURAT BEBAS TANGGUNGAN LAB
    // ─────────────────────────────────────────────────────────────

    public function indexBebasTanggungan(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BebasTanggungan::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = BebasTanggungan::query()->with(['mahasiswa', 'approver']);

        // Jika user adalah mahasiswa biasa, batasi hanya melihat permohonannya sendiri
        if (! $request->user()->hasRole('superadmin') && ! $request->user()->hasRole('admin') && ! $request->user()->hasRole('admin_sarpras') && ! $request->user()->hasRole('admin_laboratorium')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhereHas('mahasiswa', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $allowedSort = ['created_at', 'tanggal_pengajuan', 'status'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar permohonan bebas tanggungan lab berhasil diambil',
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

    public function storeBebasTanggungan(BebasTanggunganRequest $request): JsonResponse
    {
        Gate::authorize('create', BebasTanggungan::class);

        $bebasTanggungan = $this->laboratoriumService->applyBebasTanggungan(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan surat bebas tanggungan lab berhasil diajukan',
            'data' => $bebasTanggungan->load('mahasiswa'),
        ], 201);
    }

    public function showBebasTanggungan(BebasTanggungan $bebasTanggungan): JsonResponse
    {
        Gate::authorize('view', $bebasTanggungan);

        $kelayakan = $this->laboratoriumService->checkKelayakanBebasTanggungan($bebasTanggungan->mahasiswa);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail permohonan bebas tanggungan lab berhasil diambil',
            'data' => array_merge($bebasTanggungan->load(['mahasiswa', 'approver'])->toArray(), [
                'kelayakan_lab' => $kelayakan,
            ]),
        ]);
    }

    public function approveBebasTanggungan(ApproveBebasTanggunganRequest $request, BebasTanggungan $bebasTanggungan): JsonResponse
    {
        Gate::authorize('approve', $bebasTanggungan);

        $result = $this->laboratoriumService->approveBebasTanggungan(
            $bebasTanggungan,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status surat bebas tanggungan lab berhasil diproses',
            'data' => $result,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. ENDPOINTS: KALIBRASI ALAT PRESISI
    // ─────────────────────────────────────────────────────────────

    public function indexKalibrasi(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AlatKalibrasi::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = $this->laboratoriumService->getScopedKalibrasiQuery($request->user())
            ->with(['aset.ruangan.gedung', 'vendor']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('institusi_kalibrasi', 'like', "%{$search}%")
                    ->orWhere('nomor_sertifikat', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vq) use ($search) {
                        $vq->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('aset', function ($asetQuery) use ($search) {
                        $asetQuery->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode_aset', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        if ($request->filled('status_kelayakan')) {
            $query->where('status_kelayakan', $request->status_kelayakan);
        }

        if ($request->boolean('mendekati_kadaluarsa')) {
            $query->whereBetween('tanggal_kadaluarsa', [now()->toDateString(), now()->addDays(30)->toDateString()]);
        }

        $allowedSort = ['created_at', 'tanggal_kalibrasi', 'tanggal_kadaluarsa', 'status_kelayakan'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kalibrasi alat presisi berhasil diambil',
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
                'vendor_id' => $request->vendor_id,
                'status_kelayakan' => $request->status_kelayakan,
                'mendekati_kadaluarsa' => $request->boolean('mendekati_kadaluarsa'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function storeKalibrasi(AlatKalibrasiRequest $request): JsonResponse
    {
        Gate::authorize('create', AlatKalibrasi::class);

        $payload = $request->validated();
        if (empty($payload['institusi_kalibrasi']) && !empty($payload['vendor_id'])) {
            $vendor = \App\Models\Sinapra\MasterVendor::find($payload['vendor_id']);
            if ($vendor) {
                $payload['institusi_kalibrasi'] = $vendor->nama;
            }
        }

        $kalibrasi = AlatKalibrasi::create($payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Data kalibrasi alat berhasil dicatat',
            'data' => $kalibrasi->load(['aset.ruangan.gedung', 'vendor']),
        ], 201);
    }

    public function showKalibrasi(AlatKalibrasi $alatKalibrasi): JsonResponse
    {
        Gate::authorize('view', $alatKalibrasi);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kalibrasi alat berhasil diambil',
            'data' => $alatKalibrasi->load(['aset.ruangan.gedung', 'vendor']),
        ]);
    }

    public function updateKalibrasi(AlatKalibrasiRequest $request, AlatKalibrasi $alatKalibrasi): JsonResponse
    {
        Gate::authorize('update', $alatKalibrasi);

        $payload = $request->validated();
        if (empty($payload['institusi_kalibrasi']) && !empty($payload['vendor_id'])) {
            $vendor = \App\Models\Sinapra\MasterVendor::find($payload['vendor_id']);
            if ($vendor) {
                $payload['institusi_kalibrasi'] = $vendor->nama;
            }
        }

        $alatKalibrasi->update($payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Data kalibrasi alat berhasil diperbarui',
            'data' => $alatKalibrasi->fresh(['aset.ruangan.gedung', 'vendor']),
        ]);
    }

    public function destroyKalibrasi(AlatKalibrasi $alatKalibrasi): JsonResponse
    {
        Gate::authorize('delete', $alatKalibrasi);

        $alatKalibrasi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data kalibrasi alat berhasil dihapus',
            'data' => null,
        ]);
    }
}
