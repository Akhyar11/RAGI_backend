<?php

namespace App\Http\Controllers\Sinapra;

use App\Http\Controllers\Controller;
use App\Models\KategoriAset;
use App\Models\Aset;
use App\Services\Sinapra\AsetService;
use App\Http\Requests\Sinapra\KategoriAsetRequest;
use App\Http\Requests\Sinapra\AsetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AsetController extends Controller
{
    public function __construct(private AsetService $service) {}

    // ── KATEGORI ASET ENDPOINTS ───────────────────────────────────────────

    public function indexKategori(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KategoriAset::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = KategoriAset::with(['induk', 'subKategori'])->withCount('aset');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'kode', 'nama', 'masa_manfaat_tahun'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kategori aset berhasil diambil',
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
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function storeKategori(KategoriAsetRequest $request): JsonResponse
    {
        $this->authorize('create', KategoriAset::class);

        $kategori = $this->service->createKategoriAset($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori aset berhasil ditambahkan',
            'data' => $kategori->load('induk'),
        ], 201);
    }

    public function showKategori(KategoriAset $kategori): JsonResponse
    {
        $this->authorize('view', $kategori);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail kategori aset berhasil diambil',
            'data' => $kategori->load(['induk', 'subKategori', 'aset']),
        ]);
    }

    public function updateKategori(KategoriAsetRequest $request, KategoriAset $kategori): JsonResponse
    {
        $this->authorize('update', $kategori);

        $updated = $this->service->updateKategoriAset($kategori, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori aset berhasil diperbarui',
            'data' => $updated->load('induk'),
        ]);
    }

    public function destroyKategori(KategoriAset $kategori): JsonResponse
    {
        $this->authorize('delete', $kategori);

        $this->service->deleteKategoriAset($kategori);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori aset berhasil dihapus',
            'data' => null,
        ]);
    }

    // ── ASET ENDPOINTS ───────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Aset::class);

        $perPage = min(100, $request->integer('per_page', 15));
        $query = Aset::with(['kategori', 'ruangan.gedung', 'penanggungJawab.unitKerja', 'programStudi:id,kode_prodi,nama,jenjang']);

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->kategori_id);
        }

        if ($request->filled('ruangan_id')) {
            $query->where('ruangan_id', $request->ruangan_id);
        }

        if ($request->filled('program_studi_id')) {
            if ($request->program_studi_id === 'null' || $request->program_studi_id === 'umum') {
                $query->whereNull('program_studi_id');
            } else {
                $query->where('program_studi_id', $request->program_studi_id);
            }
        }

        if ($request->filled('penanggung_jawab_pegawai_id')) {
            $query->where('penanggung_jawab_pegawai_id', $request->penanggung_jawab_pegawai_id);
        }

        if ($request->filled('kondisi')) {
            $query->where('kondisi', $request->kondisi);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $user = $request->user();
        if ($user && $user->isSinapraLaboranRestricted()) {
            $query->forLaboran($user);
        }

        if ($request->filled('is_borrowable')) {
            $query->where('is_borrowable', $request->boolean('is_borrowable'));
        }

        if ($request->filled('is_lab_asset')) {
            $query->where('is_lab_asset', $request->boolean('is_lab_asset'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_aset', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('merk', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $allowedSort = ['created_at', 'kode_aset', 'nama', 'harga_perolehan', 'nilai_buku', 'tanggal_perolehan'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar aset berhasil diambil',
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
                'kategori_id' => $request->kategori_id,
                'ruangan_id' => $request->ruangan_id,
                'program_studi_id' => $request->program_studi_id,
                'kondisi' => $request->kondisi,
                'status' => $request->status,
                'is_borrowable' => $request->is_borrowable,
                'is_lab_asset' => $request->is_lab_asset,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function store(AsetRequest $request): JsonResponse
    {
        $this->authorize('create', Aset::class);

        $aset = $this->service->createAset($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data aset berhasil ditambahkan',
            'data' => $aset->load(['kategori', 'ruangan.gedung', 'penanggungJawab.unitKerja', 'programStudi:id,kode_prodi,nama,jenjang']),
        ], 201);
    }

    public function show(Aset $aset): JsonResponse
    {
        $this->authorize('view', $aset);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail aset berhasil diambil',
            'data' => $aset->load(['kategori', 'ruangan.gedung', 'penanggungJawab.unitKerja', 'programStudi:id,kode_prodi,nama,jenjang', 'maintenanceLogs', 'peminjaman', 'riwayatPenyusutan.jurnalUmum']),
        ]);
    }

    public function update(AsetRequest $request, Aset $aset): JsonResponse
    {
        $this->authorize('update', $aset);

        $updated = $this->service->updateAset($aset, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data aset berhasil diperbarui',
            'data' => $updated->load(['kategori', 'ruangan.gedung', 'penanggungJawab.unitKerja', 'programStudi:id,kode_prodi,nama,jenjang']),
        ]);
    }

    public function destroy(Aset $aset): JsonResponse
    {
        $this->authorize('delete', $aset);

        $this->service->deleteAset($aset);

        return response()->json([
            'status' => 'success',
            'message' => 'Data aset berhasil dihapus',
            'data' => null,
        ]);
    }

    public function hitungPenyusutan(Aset $aset): JsonResponse
    {
        $this->authorize('view', $aset);

        $nilaiBuku = $this->service->hitungNilaiBuku($aset);

        return response()->json([
            'status' => 'success',
            'message' => 'Estimasi penyusutan nilai buku aset berhasil dihitung',
            'data' => [
                'aset_id' => $aset->id,
                'kode_aset' => $aset->kode_aset,
                'nama' => $aset->nama,
                'harga_perolehan' => (float) $aset->harga_perolehan,
                'nilai_buku_saat_ini' => $nilaiBuku,
            ],
        ]);
    }

    public function getLabel(Aset $aset): JsonResponse
    {
        $this->authorize('view', $aset);

        $labelData = $this->service->generateLabelData($aset);

        return response()->json([
            'status' => 'success',
            'message' => 'Data label barcode & QR code aset berhasil diambil',
            'data' => $labelData,
        ]);
    }

    public function getBatchLabels(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Aset::class);

        $request->validate([
            'aset_ids' => ['required', 'array', 'min:1', 'max:100'],
            'aset_ids.*' => ['integer', 'exists:sinapra_aset,id'],
        ]);

        $labels = $this->service->generateBatchLabelData($request->input('aset_ids'));

        return response()->json([
            'status' => 'success',
            'message' => 'Data label barcode & QR code batch aset berhasil diambil',
            'data' => $labels,
        ]);
    }

    /**
     * Memposting beban penyusutan aset ke Jurnal Umum SIKEU.
     */
    public function postJurnalPenyusutan(Request $request, Aset $aset): JsonResponse
    {
        $this->authorize('update', $aset);

        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $tahun = $validated['tahun'] ?? (int) date('Y');

        try {
            $riwayat = $this->service->postJurnalPenyusutan(
                $aset,
                $tahun,
                $request->user()->id,
                $validated['catatan'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => "Jurnal penyusutan aset periode tahun {$tahun} berhasil diposting ke SIKEU",
                'data' => $riwayat,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mendapatkan daftar riwayat jurnal penyusutan aset.
     */
    public function getRiwayatPenyusutan(Aset $aset): JsonResponse
    {
        $this->authorize('view', $aset);

        $riwayat = $this->service->getRiwayatPenyusutan($aset);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat penyusutan aset berhasil diambil',
            'data' => $riwayat,
        ]);
    }
}
