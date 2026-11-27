<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\GetBiayaPendaftaranRequest;
use App\Http\Requests\Spmb\ResolveGelombangRequest;
use App\Services\Spmb\SpmbGelombangResolver;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\System\MasterReferensi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
            // Menerima true/false/1/0 maupun aktif/nonaktif. Nilai teks seperti "aktif"
            // sebelumnya terbaca false oleh filter_var sehingga hanya jalur nonaktif yang tampil.
            $status = strtolower(trim((string) $request->input('status')));
            $isActive = match ($status) {
                'aktif', 'active' => true,
                'nonaktif', 'tidak_aktif', 'inactive' => false,
                default => filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            };
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
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
            'pilih_perguruan_tinggi' => 'sometimes|boolean',
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
            'pilih_perguruan_tinggi' => 'sometimes|boolean',
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
        $query = GelombangPenerimaan::with(['jalurMasuk', 'daftarJalur']);

        if ($request->filled('nama')) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        if ($request->filled('jalur_masuk_id')) {
            $query->untukJalur($request->integer('jalur_masuk_id'));
        }

        if ($request->boolean('umum')) {
            $query->umum();
        }

        if ($request->filled('tanggal_buka')) {
            $query->where('tanggal_buka_siklus', '>=', $request->tanggal_buka);
        }

        if ($request->filled('tanggal_tutup')) {
            $query->where('tanggal_tutup_siklus', '<=', $request->tanggal_tutup);
        }

        if ($request->filled('kuota')) {
            if ($request->kuota === 'tersedia') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) > 0');
            } elseif ($request->kuota === 'penuh') {
                $query->whereRaw('(kuota_total - COALESCE(kuota_terisi, 0)) <= 0');
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
        $allowedSortColumns = ['id', 'nama', 'tanggal_buka', 'tanggal_tutup', 'kuota_total', 'status', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSortColumns) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'tanggal_buka' => 'tanggal_buka_siklus',
            'tanggal_tutup' => 'tanggal_tutup_siklus',
        ];
        $query->orderBy($sortColumns[$sortBy] ?? $sortBy, $sortOrder);

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
                'status' => $request->status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Tentukan gelombang untuk jalur masuk yang dipilih (otomatis, pilih, atau tidak tersedia).
     */
    public function resolveGelombang(ResolveGelombangRequest $request, SpmbGelombangResolver $resolver): JsonResponse
    {
        $jalurId = $request->integer('jalur_masuk_id');
        $result = $resolver->forJalur($jalurId, $request->input('konteks') === SpmbGelombangResolver::KONTEKS_ONLINE);

        return response()->json([
            'status' => 'success',
            'message' => 'Gelombang untuk jalur berhasil ditentukan.',
            'data' => [
                'jalur_masuk_id' => $jalurId,
                'mode' => $result['mode'],
                'sumber' => $result['sumber'],
                'gelombang' => $result['gelombang'],
                'options' => $result['options']->values(),
            ],
        ]);
    }

    /**
     * Show Gelombang
     */
    public function showGelombang($id): JsonResponse
    {
        $gelombang = GelombangPenerimaan::with(['jalurMasuk', 'daftarJalur'])->findOrFail($id);
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
            'jalur_masuk_id' => 'nullable|exists:spmb_jalur_masuk,id',
            'jalur_masuk_ids' => 'nullable|array',
            'jalur_masuk_ids.*' => 'integer|distinct|exists:spmb_jalur_masuk,id',
            'nama' => 'required|string',
            'tanggal_buka' => ['required', 'string', 'regex:/^\\d{2}-\\d{2}$/'],
            'tanggal_tutup' => ['required', 'string', 'regex:/^\\d{2}-\\d{2}$/'],
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'prohibited',
            'potongan_biaya_daftar_ulang' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        $this->validateCyclePeriodDates($validated);

        $jalurIds = $this->extractJalurIds($validated);
        unset($validated['jalur_masuk_id'], $validated['jalur_masuk_ids']);

        $gelombang = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $jalurIds) {
            $created = GelombangPenerimaan::create($validated)->refresh();
            $created->daftarJalur()->sync($jalurIds);

            return $created;
        });
        $gelombang->load(['jalurMasuk', 'daftarJalur']);

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
            'jalur_masuk_id' => 'nullable|exists:spmb_jalur_masuk,id',
            'jalur_masuk_ids' => 'nullable|array',
            'jalur_masuk_ids.*' => 'integer|distinct|exists:spmb_jalur_masuk,id',
            'nama' => 'required|string',
            'tanggal_buka' => ['required', 'string', 'regex:/^\\d{2}-\\d{2}$/'],
            'tanggal_tutup' => ['required', 'string', 'regex:/^\\d{2}-\\d{2}$/'],
            'tanggal_pengumuman' => 'nullable|date',
            'kuota_total' => 'required|integer|min:1',
            'biaya_pendaftaran' => 'prohibited',
            'potongan_biaya_daftar_ulang' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:draft,aktif,ditutup,selesai',
        ]);

        $this->validateCyclePeriodDates($validated);

        // Pemetaan jalur hanya diubah bila dikirim; array kosong = gelombang umum.
        $syncJalur = $request->exists('jalur_masuk_ids') || $request->exists('jalur_masuk_id');
        $jalurIds = $syncJalur ? $this->extractJalurIds($validated) : [];
        unset($validated['jalur_masuk_id'], $validated['jalur_masuk_ids']);
        if ($syncJalur) {
            $validated['jalur_masuk_id'] = null; // kolom lama tidak dipakai lagi
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($gelombang, $validated, $syncJalur, $jalurIds) {
            $gelombang->update($validated);
            if ($syncJalur) {
                $gelombang->daftarJalur()->sync($jalurIds);
            }
        });
        $gelombang->refresh()->load(['jalurMasuk', 'daftarJalur']);

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
     * Kumpulkan ID jalur dari `jalur_masuk_ids` (pilihan ganda) dan `jalur_masuk_id` tunggal lama.
     *
     * @param  array<string,mixed>  $validated
     * @return list<int>
     */
    private function extractJalurIds(array $validated): array
    {
        $ids = array_map('intval', $validated['jalur_masuk_ids'] ?? []);
        if (! empty($validated['jalur_masuk_id'])) {
            $ids[] = (int) $validated['jalur_masuk_id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Validate recurring MM-DD boundaries, allowing February 29 in leap years.
     * An end boundary before the start boundary intentionally means the cycle
     * wraps into the next calendar year, so an after_or_equal date rule is not valid here.
     */
    private function validateCyclePeriodDates(array $validated): void
    {
        $start = $validated['tanggal_buka'];
        $end = $validated['tanggal_tutup'];

        foreach (['tanggal_buka', 'tanggal_tutup'] as $field) {
            [$month, $day] = array_map('intval', explode('-', $validated[$field]));
            if (! checkdate($month, $day, 2000)) {
                throw ValidationException::withMessages([
                    $field => 'Tanggal periode tidak valid.',
                ]);
            }
        }

        // Resolve the closing boundary in the next cycle when MM-DD wraps.
        [$endMonth, $endDay] = array_map('intval', explode('-', $end));
        $endYear = $end < $start ? 2004 : 2000;
        if (! checkdate($endMonth, $endDay, $endYear)) {
            throw ValidationException::withMessages([
                'tanggal_tutup' => 'Tanggal tutup periode tidak valid.',
            ]);
        }
    }

    /**
     * Rincian komponen biaya (beban awal pendaftaran & daftar ulang)
     * untuk tipe jalur masuk + program studi tertentu.
     */
    public function getBiayaPendaftaran(GetBiayaPendaftaranRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = app(\App\Services\Spmb\MasterBiayaService::class);
        $jalurMasukId = (int) $validated['jalur_masuk_id'];
        $prodiId = (int) $validated['program_studi_id'];

        $map = function ($items) {
            return $items->map(function ($item) {
                return [
                    'komponen_biaya_id' => $item->komponen_biaya_id,
                    'kode' => $item->komponenBiaya->kode,
                    'nama' => $item->komponenBiaya->nama,
                    'kategori' => $item->komponenBiaya->kategori,
                    'nominal' => (float) $item->nominal,
                ];
            })->values();
        };

        $pendaftaran = $map($service->getKomponenBeban($jalurMasukId, $prodiId, true));
        $daftarUlang = $map($service->getKomponenBeban($jalurMasukId, $prodiId, false));

        // Sumber biaya pendaftaran hanya komponen Master Biaya SPMB.
        $hasMasterConfig = $pendaftaran->isNotEmpty() || $daftarUlang->isNotEmpty();

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian biaya pendaftaran berhasil dimuat.',
            'data' => [
                'source' => $hasMasterConfig ? 'master' : 'none',
                'beban_pendaftaran' => $pendaftaran,
                'total_pendaftaran' => (float) $pendaftaran->sum('nominal'),
                'beban_daftar_ulang' => $daftarUlang,
                'total_daftar_ulang' => (float) $daftarUlang->sum('nominal'),
            ],
        ]);
    }

    /**
     * Get all active Program Studi for SPMB
     */
    public function getProgramStudi(Request $request): JsonResponse
    {
        $query = \App\Models\Siakad\ProgramStudi::where('is_active', true);

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
     * Get all active Tahun Akademik for SPMB (Sumber utama dari SIAKAD)
     */
    public function getTahunAkademik(): JsonResponse
    {
        $tahun = collect();
        try {
            $tableName = \Illuminate\Support\Facades\Schema::hasTable('siakad_tahun_akademik')
                ? 'siakad_tahun_akademik'
                : null;

            if ($tableName) {
                $tahun = \Illuminate\Support\Facades\DB::table($tableName)
                    ->whereNull('deleted_at')
                    ->select(
                        'id',
                        'kode',
                        'nama',
                        'tahun_mulai',
                        'tahun_selesai',
                        'is_active',
                        \Illuminate\Support\Facades\DB::raw('is_active as is_current')
                    )
                    ->orderBy('kode', 'desc')
                    ->get();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal memuat tahun akademik dari siakad: ' . $e->getMessage());
        }

        if ($tahun->isEmpty()) {
            $tahun = collect([
                ['id' => 1, 'nama' => '2026/2027 Ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_active' => true, 'is_current' => true],
                ['id' => 2, 'nama' => '2025/2026 Genap', 'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'is_active' => false, 'is_current' => false],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $tahun
        ]);
    }
}
