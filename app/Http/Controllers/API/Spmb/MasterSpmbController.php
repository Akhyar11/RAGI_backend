<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\MasterTipeJalur;
use App\Models\System\MasterReferensi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterSpmbController extends Controller
{
    /**
     * Get referensi by tipe
     */
    public function getReferensi($tipe): JsonResponse
    {
        $data = MasterReferensi::where('tipe', $tipe)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Get all Master Tipe Jalur
     */
    public function getMasterTipeJalur(Request $request): JsonResponse
    {
        $query = MasterTipeJalur::with('alur');

        if ($request->filled('name') || $request->filled('search')) {
            $search = $request->input('name', $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', $request->input('orderBy', 'nama'));
        $sortDir = $request->input('sort_dir', $request->input('orderDir', 'asc'));
        $allowedSorts = ['id', 'kode', 'nama', 'created_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'nama';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        if ($request->has('page')) {
            $limit = (int) $request->input('limit', 10);
            $paginated = $query->paginate($limit);
            return response()->json([
                'status' => 'success',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ]
            ]);
        }

        $tipeJalur = $query->get();
        return response()->json([
            'status' => 'success',
            'data' => $tipeJalur
        ]);
    }

    /**
     * Store Master Tipe Jalur
     */
    public function storeMasterTipeJalur(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:core_master_tipe_jalur,kode',
            'nama' => 'required|string|max:255',
            'alur' => 'nullable|array',
            'alur.*.nama_tahap' => 'required|string|max:255',
            'alur.*.urutan' => 'nullable|integer',
        ]);

        $item = MasterTipeJalur::create([
            'kode' => $validated['kode'],
            'nama' => $validated['nama']
        ]);

        if (!empty($validated['alur'])) {
            foreach ($validated['alur'] as $idx => $alurItem) {
                $item->alur()->create([
                    'nama_tahap' => $alurItem['nama_tahap'],
                    'urutan' => $alurItem['urutan'] ?? ($idx + 1),
                ]);
            }
        }

        $item->load('alur');

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe jalur berhasil ditambahkan',
            'data' => $item
        ], 201);
    }

    /**
     * Update Master Tipe Jalur
     */
    public function updateMasterTipeJalur(Request $request, $id): JsonResponse
    {
        $item = MasterTipeJalur::findOrFail($id);

        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:core_master_tipe_jalur,kode,' . $id,
            'nama' => 'required|string|max:255',
            'alur' => 'nullable|array',
            'alur.*.nama_tahap' => 'required|string|max:255',
            'alur.*.urutan' => 'nullable|integer',
        ]);

        $item->update([
            'kode' => $validated['kode'],
            'nama' => $validated['nama']
        ]);

        if (isset($validated['alur'])) {
            $item->alur()->delete();
            foreach ($validated['alur'] as $idx => $alurItem) {
                $item->alur()->create([
                    'nama_tahap' => $alurItem['nama_tahap'],
                    'urutan' => $alurItem['urutan'] ?? ($idx + 1),
                ]);
            }
        }

        $item->load('alur');

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe jalur berhasil diperbarui',
            'data' => $item
        ]);
    }

    /**
     * Delete Master Tipe Jalur
     */
    public function destroyMasterTipeJalur($id): JsonResponse
    {
        $item = MasterTipeJalur::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe jalur berhasil dihapus'
        ]);
    }

    /**
     * Get all Jalur Masuk
     */
    public function getJalurMasuk(Request $request): JsonResponse
    {
        $query = JalurMasuk::query();

        if ($request->filled('search') || $request->filled('name')) {
            $search = $request->input('search', $request->input('name'));
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', filter_var($request->input('status'), FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['id', 'kode', 'nama', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts) ? $request->sort_by : 'created_at';
        $sortOrder = in_array(strtolower($request->input('sort_order', $request->input('sort_dir', 'desc'))), ['asc', 'desc'])
            ? strtolower($request->input('sort_order', $request->input('sort_dir', 'desc')))
            : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data jalur penerimaan berhasil dimuat.',
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
                'search' => $request->search,
                'status' => $request->status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Show Jalur Masuk
     */
    public function showJalurMasuk($id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $jalur
        ]);
    }

    /**
     * Store Jalur Masuk
     */
    public function storeJalurMasuk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => 'required|string|unique:spmb_jalur_masuk,kode',
            'nama' => 'required|string',
            'deskripsi' => 'nullable|string',
            'ada_wawancara' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $jalur = JalurMasuk::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil dibuat.',
            'data' => $jalur
        ], 201);
    }

    /**
     * Update Jalur Masuk
     */
    public function updateJalurMasuk(Request $request, $id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);

        $validated = $request->validate([
            'kode' => 'required|string|unique:spmb_jalur_masuk,kode,' . $jalur->id,
            'nama' => 'required|string',
            'deskripsi' => 'nullable|string',
            'ada_wawancara' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $jalur->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil diperbarui.',
            'data' => $jalur
        ]);
    }

    /**
     * Destroy Jalur Masuk
     */
    public function destroyJalurMasuk($id): JsonResponse
    {
        $jalur = JalurMasuk::findOrFail($id);
        $jalur->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jalur masuk berhasil dihapus.'
        ]);
    }

    /**
     * Get all Gelombang Penerimaan (Active)
     */
    public function getGelombang(Request $request): JsonResponse
    {
        $query = GelombangPenerimaan::with(['jalurMasuk', 'masterBiaya', 'tahunAkademik']);

        if ($request->filled('nama')) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        if ($request->filled('jalur_masuk_id')) {
            $query->where('jalur_masuk_id', $request->jalur_masuk_id);
        }

        if ($request->filled('tanggal_buka')) {
            $query->whereDate('tanggal_buka', '>=', $request->tanggal_buka);
        }

        if ($request->filled('tanggal_tutup')) {
            $query->whereDate('tanggal_tutup', '<=', $request->tanggal_tutup);
        }

        if ($request->filled('kuota')) {
            if ($request->kuota === 'tersedia') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) > 0');
            } elseif ($request->kuota === 'penuh') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) <= 0');
            }
        }

        if ($request->filled('biaya')) {
            if ($request->biaya === 'gratis') {
                $query->where(function ($q) {
                    $q->whereNull('biaya_pendaftaran')->orWhere('biaya_pendaftaran', 0);
                });
            } elseif ($request->biaya === 'berbayar') {
                $query->where('biaya_pendaftaran', '>', 0);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
        $allowedSortColumns = ['id', 'nama', 'tanggal_buka', 'tanggal_tutup', 'kuota_total', 'biaya_pendaftaran', 'status', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data gelombang berhasil dimuat.',
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
                'search' => $request->search,
                'nama' => $request->nama,
                'jalur_masuk_id' => $request->jalur_masuk_id,
                'tanggal_buka' => $request->tanggal_buka,
                'tanggal_tutup' => $request->tanggal_tutup,
                'kuota' => $request->kuota,
                'biaya' => $request->biaya,
                'status' => $request->status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Show Gelombang
     */
    public function showGelombang($id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::with(['jalurMasuk', 'masterBiaya', 'tahunAkademik'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'message' => 'Data detail gelombang berhasil dimuat.',
            'data' => $gelombang
        ]);
    }

    /**
     * Store Gelombang
     */
    public function storeGelombang(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:spmb_jalur_masuk,id',
            'tahun_akademik_id' => 'required|integer', // assuming it exists
            'master_biaya_id' => 'nullable|exists:sikeu_master_biaya,id',
            'nama' => 'required|string',
            'tanggal_buka' => 'required|date',
            'tanggal_tutup' => 'required|date|after_or_equal:tanggal_buka',
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        if (empty($validated['biaya_pendaftaran']) && !empty($validated['master_biaya_id'])) {
            $mb = \App\Models\Sikeu\MasterBiaya::find($validated['master_biaya_id']);
            $validated['biaya_pendaftaran'] = $mb ? (float)$mb->nominal_standar : 0;
        }

        if ($validated['status'] === 'aktif') {
            $this->deactivateOtherActiveGelombang($validated['jalur_masuk_id']);
        }

        $gelombang = GelombangPenerimaan::create($validated);
        $gelombang->load(['jalurMasuk', 'masterBiaya']);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil dibuat.',
            'data' => $gelombang
        ], 201);
    }

    /**
     * Update Gelombang
     */
    public function updateGelombang(Request $request, $id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::findOrFail($id);

        $validated = $request->validate([
            'jalur_masuk_id' => 'required|exists:spmb_jalur_masuk,id',
            'tahun_akademik_id' => 'required|integer',
            'master_biaya_id' => 'nullable|exists:sikeu_master_biaya,id',
            'nama' => 'required|string',
            'tanggal_buka' => 'required|date',
            'tanggal_tutup' => 'required|date|after_or_equal:tanggal_buka',
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        if (!isset($validated['biaya_pendaftaran']) && !empty($validated['master_biaya_id'])) {
            $mb = \App\Models\Sikeu\MasterBiaya::find($validated['master_biaya_id']);
            $validated['biaya_pendaftaran'] = $mb ? (float)$mb->nominal_standar : 0;
        }

        if ($validated['status'] === 'aktif') {
            $this->deactivateOtherActiveGelombang($validated['jalur_masuk_id'], $id);
        }

        $gelombang->update($validated);
        $gelombang->load(['jalurMasuk', 'masterBiaya']);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil diperbarui.',
            'data' => $gelombang
        ]);
    }

    /**
     * Destroy Gelombang
     */
    public function destroyGelombang($id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::findOrFail($id);
        $gelombang->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang penerimaan berhasil dihapus.'
        ]);
    }

    /**
     * Pastikan hanya satu gelombang yang berstatus 'aktif' per jalur masuk.
     * Saat sebuah gelombang di-set aktif, gelombang lain di jalur yang sama
     * otomatis diubah ke 'ditutup'.
     *
     * @param int $jalurMasukId
     * @param int|null $exceptId Abaikan gelombang dengan id ini (saat update).
     */
    private function deactivateOtherActiveGelombang(int $jalurMasukId, ?int $exceptId = null): void
    {
        GelombangPenerimaan::where('jalur_masuk_id', $jalurMasukId)
            ->where('status', 'aktif')
            ->when($exceptId !== null, function ($q) use ($exceptId) {
                $q->where('id', '!=', $exceptId);
            })
            ->update(['status' => 'ditutup']);
    }

    /**
     * Get all active Program Studi for SPMB
     */
    public function getProgramStudi(Request $request): JsonResponse
    {
        $query = \App\Models\Spmb\MasterProgramStudi::where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode_prodi', 'like', "%{$search}%");
            });
        }

        $sortBy = in_array($request->input('sort_by'), ['id', 'nama', 'kode_prodi'], true)
            ? $request->input('sort_by')
            : 'id';
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        if (!$request->has('page')) {
            return response()->json(['status' => 'success', 'data' => $query->get()]);
        }

        $paginated = $query->paginate((int) $request->input('limit', 15));
        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Get all active Tahun Akademik for SPMB
     */
    public function getTahunAkademik(): JsonResponse
    {
        $tahun = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('spmb_master_tahun_akademik')) {
                $tahun = \Illuminate\Support\Facades\DB::table('spmb_master_tahun_akademik')
                    ->select('id', 'kode', 'nama', 'is_active', 'is_current')
                    ->orderBy('kode', 'desc')
                    ->get();
            }
        } catch (\Throwable $e) {
            // Graceful fallback if table not migrated yet
        }

        if ($tahun->isEmpty()) {
            $tahun = collect([
                ['id' => 1, 'nama' => '2026/2027 Ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_active' => true],
                ['id' => 2, 'nama' => '2025/2026 Genap', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'is_active' => false],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $tahun
        ]);
    }
}
