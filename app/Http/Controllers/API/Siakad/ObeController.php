<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\Cpmk;
use App\Models\Siakad\CpmkProdi;
use App\Models\Siakad\RpsReferensi;
use App\Models\Siakad\SubCpmk;
use App\Models\Siakad\ProfilLulusan;
use App\Models\Siakad\BahanKajian;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\BankSoal;
use App\Models\Siakad\BankSoalKategori;
use App\Models\Siakad\BankSoalOpsi;
use App\Http\Requests\Siakad\Obe\StoreBankSoalRequest;
use App\Http\Requests\Siakad\Obe\StoreBankSoalKategoriRequest;
use App\Http\Requests\Siakad\Obe\StoreBankSoalOpsiRequest;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Services\Siakad\SiakadAkademikService;
use App\Http\Requests\Siakad\StoreCplRequest;
use App\Http\Requests\Siakad\StoreBahanKajianRequest;
use App\Http\Requests\Siakad\StoreCpmkProdiRequest;
use App\Http\Requests\Siakad\StoreKelasKomponenRequest;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ObeController extends Controller
{
    protected SiakadAkademikService $akademikService;

    public function __construct(SiakadAkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    // --- CPL (Capaian Pembelajaran Lulusan) ---
    public function getCpl(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();
        $query = Cpl::with(['programStudi', 'kurikulum', 'jenisCpl']);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            if ($allowedProdiIds->isNotEmpty()) {
                $query->whereIn('program_studi_id', $allowedProdiIds);
            }
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('kategori')) {
            $cat = $request->kategori;
            $query->where(fn($q) => $q->where('kategori', $cat)->orWhere('jenis_list', 'like', "%{$cat}%"));
        }
        if ($request->filled('jenis_cpl_id')) {
            $query->where('jenis_cpl_id', $request->integer('jenis_cpl_id'));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('kode_cpl', 'like', "%{$s}%")->orWhere('deskripsi', 'like', "%{$s}%"));
        }
        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['kode_cpl', 'kategori', 'created_at', 'id'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true)
            ? $request->query('sort_by')
            : 'kode_cpl';
        $sortOrder = strtolower((string) $request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar CPL berhasil diambil',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar CPL berhasil diambil',
            'data' => $query->get()
        ]);
    }

    public function storeCpl(StoreCplRequest $request)
    {
        // Otorisasi sudah divalidasi pada StoreCplRequest::authorize() yang memeriksa
        // prodi aktif user (Tim Kurikulum / Kaprodi), bukan hanya permission global.
        $validated = $request->validated();

        if (empty($validated['program_studi_id']) && !empty($validated['kurikulum_id'])) {
            $kur = \App\Models\Siakad\Kurikulum::find($validated['kurikulum_id']);
            $validated['program_studi_id'] = $kur?->program_studi_id;
        }

        if (empty($validated['program_studi_id'])) {
            $user = $request->user();
            $prodiIds = $user && method_exists($user, 'getSiakadProdiIds') ? $user->getSiakadProdiIds() : collect();
            $validated['program_studi_id'] = $prodiIds->first();
            if (empty($validated['program_studi_id'])) {
                return response()->json(['status' => 'error', 'message' => 'Program studi wajib ditentukan.'], 422);
            }
        }

        $cpl = Cpl::updateOrCreate(
            ['program_studi_id' => $validated['program_studi_id'], 'kode_cpl' => $validated['kode_cpl']],
            [
                'kurikulum_id' => $validated['kurikulum_id'] ?? null,
                'jenis_cpl_id' => $validated['jenis_cpl_id'] ?? null,
                'kategori' => $validated['kategori'],
                'jenis_list' => $validated['jenis_list'] ?? null,
                'deskripsi' => $validated['deskripsi'],
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_cpl',
                recordId: $cpl->id,
                oldValues: null,
                newValues: $cpl->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'CPL berhasil disimpan',
            'data' => $cpl->load(['programStudi', 'kurikulum', 'jenisCpl'])
        ], 201);
    }

    public function updateCpl(StoreCplRequest $request, int $id)
    {
        // Sama seperti storeCpl: otorisasi tiap prodi ditangani StoreCplRequest.
        $cpl = Cpl::findOrFail($id);
        $old = $cpl->getOriginal();
        $validated = $request->validated();

        if (empty($validated['program_studi_id']) && !empty($validated['kurikulum_id'])) {
            $kur = \App\Models\Siakad\Kurikulum::find($validated['kurikulum_id']);
            $validated['program_studi_id'] = $kur?->program_studi_id ?? $cpl->program_studi_id;
        }

        $cpl->update([
            'program_studi_id' => $validated['program_studi_id'] ?? $cpl->program_studi_id,
            'kurikulum_id' => $validated['kurikulum_id'] ?? null,
            'jenis_cpl_id' => $validated['jenis_cpl_id'] ?? null,
            'kode_cpl' => $validated['kode_cpl'] ?? $cpl->kode_cpl,
            'kategori' => $validated['kategori'] ?? $cpl->kategori,
            'jenis_list' => $validated['jenis_list'] ?? $cpl->jenis_list,
            'deskripsi' => $validated['deskripsi'] ?? $cpl->deskripsi,
            'is_active' => $validated['is_active'] ?? $cpl->is_active,
        ]);
        $new = $cpl->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_cpl',
                recordId: $cpl->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'CPL berhasil diperbarui',
            'data' => $cpl->load(['programStudi', 'kurikulum', 'jenisCpl'])
        ]);
    }

    public function destroyCpl(Request $request, int $id)
    {
        $cpl = Cpl::findOrFail($id);

        // Otorisasi per prodi: Tim Kurikulum hanya boleh menghapus CPL prodi aktifnya.
        $user = $request->user();
        if (!$user || !$user->canManageObeForProdi((int) $cpl->program_studi_id)) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $old = $cpl->getOriginal();
        $cpl->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_cpl',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'CPL berhasil dihapus',
            'data' => null,
        ]);
    }

    // --- CPMK (Capaian Pembelajaran Mata Kuliah) ---
    public function getCpmk(Request $request)
    {
        $query = Cpmk::with(['cpl', 'subCpmks']);
        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->mata_kuliah_id);
        }
        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    // ============================================================
    // Rumusan CPMK Program Studi (CPMK-PS)
    // Terpisah dari `siakad_cpmk` yang merupakan CPMK per Mata Kuliah.
    // ============================================================

    /**
     * Daftar rumusan CPMK program studi dengan filter, sort whitelist, dan
     * pagination server-side. Program studi mengikuti prodi aktif user.
     */
    public function getCpmkProdi(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $prodiIds = $this->resolveObeProdiId($request);

        $query = CpmkProdi::with(['kurikulum', 'cpl'])
            ->whereHas('kurikulum', fn ($q) => $q->whereIn('program_studi_id', $prodiIds));

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('cpl_id')) {
            $query->where('cpl_id', $request->cpl_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('kode_cpmk', 'like', "%{$s}%")
                    ->orWhere('deskripsi', 'like', "%{$s}%");
            });
        }

        $allowedSort = ['kode_cpmk', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'kode_cpmk';
        $query->orderBy($sortBy, $request->sort_order === 'desc' ? 'desc' : 'asc');

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Daftar rumusan CPMK program studi berhasil diambil',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar rumusan CPMK program studi berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    /**
     * Simpan rumusan CPMK program studi baru (create/update berdasar kode + kurikulum).
     */
    public function storeCpmkProdi(StoreCpmkProdiRequest $request)
    {
        $validated = $request->validated();

        $cpmkProdi = CpmkProdi::updateOrCreate(
            ['kurikulum_id' => $validated['kurikulum_id'], 'kode_cpmk' => $validated['kode_cpmk']],
            [
                'cpl_id' => $validated['cpl_id'],
                'deskripsi' => $validated['deskripsi'],
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $cpmkProdi->wasRecentlyCreated ? 'create' : 'update',
                tableName: 'siakad_cpmk_prodi',
                recordId: $cpmkProdi->id,
                oldValues: null,
                newValues: $cpmkProdi->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumusan CPMK program studi berhasil disimpan',
            'data' => $cpmkProdi->load(['kurikulum', 'cpl']),
        ], 201);
    }

    public function updateCpmkProdi(StoreCpmkProdiRequest $request, int $id)
    {
        // Otorisasi per prodi ditangani StoreCpmkProdiRequest.
        $cpmkProdi = CpmkProdi::findOrFail($id);
        $old = $cpmkProdi->getOriginal();

        $validated = $request->validated();

        // Kurikulum bersifat tetap: hanya CPL, kode, dan rumusan yang dapat diubah.
        $cpmkProdi->update([
            'cpl_id' => $validated['cpl_id'],
            'kode_cpmk' => $validated['kode_cpmk'],
            'deskripsi' => $validated['deskripsi'],
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_cpmk_prodi',
                recordId: $cpmkProdi->id,
                oldValues: $old,
                newValues: $cpmkProdi->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumusan CPMK program studi berhasil diperbarui',
            'data' => $cpmkProdi->load(['kurikulum', 'cpl']),
        ]);
    }

    public function destroyCpmkProdi(Request $request, int $id)
    {
        $cpmkProdi = CpmkProdi::with('kurikulum')->findOrFail($id);

        $user = $request->user();
        if (!$user || !$user->canManageObeForProdi((int) $cpmkProdi->kurikulum?->program_studi_id)) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $old = $cpmkProdi->getOriginal();
        $cpmkProdi->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_cpmk_prodi',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumusan CPMK program studi berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * Mengambil daftar pemetaan CPL-CPMK-MK (Rumusan CPMK beserta CPL dan MK yang diampunya)
     */
    public function getPemetaanCplCpmkMk(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $prodiIds = $this->resolveObeProdiId($request);

        $query = CpmkProdi::with(['kurikulum', 'cpl', 'mataKuliahs'])
            ->whereHas('kurikulum', fn ($q) => $q->whereIn('program_studi_id', $prodiIds));

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('cpl_id')) {
            $query->where('cpl_id', $request->cpl_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('kode_cpmk', 'like', "%{$s}%")
                    ->orWhere('deskripsi', 'like', "%{$s}%")
                    ->orWhereHas('cpl', fn($c) => $c->where('kode_cpl', 'like', "%{$s}%")->orWhere('deskripsi', 'like', "%{$s}%"));
            });
        }

        $allowedSort = ['kode_cpmk', 'cpl_id', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'kode_cpmk';
        $query->orderBy($sortBy, $request->sort_order === 'desc' ? 'desc' : 'asc');

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Data pemetaan CPL-CPMK-MK berhasil dimuat',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        $items = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data pemetaan CPL-CPMK-MK berhasil dimuat',
            'data' => $items,
        ]);
    }

    /**
     * Menyimpan pemetaan Mata Kuliah ke satu Rumusan CPMK Prodi
     */
    public function syncCpmkProdiMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $validated = $request->validate([
            'cpmk_prodi_id' => 'required|exists:siakad_cpmk_prodi,id',
            'mata_kuliah_ids' => 'present|array',
            'mata_kuliah_ids.*' => 'exists:siakad_mata_kuliah,id',
        ]);

        $cpmkProdi = CpmkProdi::with('kurikulum')->findOrFail($validated['cpmk_prodi_id']);

        $user = $request->user();
        $prodiId = (int) ($cpmkProdi->kurikulum?->program_studi_id ?? 0);
        if (!$user || !$user->canManageObeForProdi($prodiId)) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $oldIds = $cpmkProdi->mataKuliahs()->pluck('siakad_mata_kuliah.id')->toArray();
        $newIds = $validated['mata_kuliah_ids'] ?? [];

        $cpmkProdi->mataKuliahs()->sync($newIds);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_cpmk_prodi_mata_kuliah',
                recordId: $cpmkProdi->id,
                oldValues: ['mata_kuliah_ids' => $oldIds],
                newValues: ['mata_kuliah_ids' => $newIds],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log sync CPMK Prodi MK: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Mata Kuliah untuk CPMK ' . $cpmkProdi->kode_cpmk . ' berhasil disimpan',
            'data' => $cpmkProdi->load(['kurikulum', 'cpl', 'mataKuliahs']),
        ]);
    }

    // ============================================================
    // Referensi RPS: Bentuk, Metode, Kriteria, Komponen
    // ============================================================

    public function getRpsReferensi(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $prodiIds = $this->resolveObeProdiId($request);

        $query = RpsReferensi::query()
            ->where(function ($q) use ($prodiIds) {
                // Tampilkan data milik prodi aktif user ATAU data global bawaan sistem
                $q->whereIn('program_studi_id', $prodiIds)
                  ->orWhereNull('program_studi_id');
            });

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                    ->orWhere('kode', 'like', "%{$s}%")
                    ->orWhere('deskripsi', 'like', "%{$s}%");
            });
        }

        $allowedSort = ['nama', 'kode', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'nama';
        $query->orderBy($sortBy, $request->sort_order === 'desc' ? 'desc' : 'asc');

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Data referensi RPS berhasil dimuat',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi RPS berhasil dimuat',
            'data' => $query->get(),
        ]);
    }

    public function storeRpsReferensi(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $prodiIds = $this->allowedObeProdiIds($request);
        $primaryProdiId = $prodiIds[0] ?? null;

        $validated = $request->validate([
            'tipe' => 'required|in:jenis_pembelajaran,bentuk,metode,kriteria,komponen',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string|max:2000',
        ]);

        // Otomatis kaitkan ke prodi aktif pengguna agar tidak bocor ke prodi lain
        if (empty($validated['program_studi_id'])) {
            $validated['program_studi_id'] = $primaryProdiId;
        } elseif (!in_array((int)$validated['program_studi_id'], $prodiIds, true) && !$user->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk menyimpan referensi pada program studi ini.');
        }

        $item = RpsReferensi::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_rps_referensi',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log store RPS Referensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi RPS berhasil disimpan',
            'data' => $item,
        ], 201);
    }

    public function updateRpsReferensi(Request $request, int $id)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = RpsReferensi::findOrFail($id);

        $user = $request->user();
        $prodiIds = $this->allowedObeProdiIds($request);
        if ($item->program_studi_id && !in_array((int)$item->program_studi_id, $prodiIds, true) && !$user->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah data prodi lain.');
        }

        $old = $item->getOriginal();

        $validated = $request->validate([
            'kode' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string|max:2000',
        ]);

        $item->update($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_rps_referensi',
                recordId: $item->id,
                oldValues: $old,
                newValues: $item->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log update RPS Referensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi RPS berhasil diperbarui',
            'data' => $item,
        ]);
    }

    public function destroyRpsReferensi(Request $request, int $id)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = RpsReferensi::findOrFail($id);

        $user = $request->user();
        $prodiIds = $this->allowedObeProdiIds($request);
        if ($item->program_studi_id && !in_array((int)$item->program_studi_id, $prodiIds, true) && !$user->isSuperAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus data prodi lain.');
        }

        $old = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_rps_referensi',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log destroy RPS Referensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data referensi RPS berhasil dihapus',
            'data' => null,
        ]);
    }

    public function storeCpmk(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'kode_cpmk' => 'required|string|max:50',
            'deskripsi' => 'required|string',
            'bobot_persentase' => 'nullable|numeric|min:0|max:100',
            'cpl_id' => 'nullable|exists:siakad_cpl,id',
        ]);

        // Kaprodi/BAAK bebas; dosen hanya untuk MK yang diampunya
        $user = $request->user();
        $priv = $user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));
        if (!$priv && $user) {
            $dosen = \App\Models\Siakad\Dosen::where('user_id', $user->id)->first();
            $mengampu = $dosen && \App\Models\Siakad\DosenPengampu::where('dosen_id', $dosen->id)
                ->whereHas('kelas', fn($q) => $q->where('mata_kuliah_id', $request->mata_kuliah_id))
                ->exists();
            if (!$mengampu) {
                return response()->json(['status' => 'error', 'message' => 'Anda hanya dapat memetakan CPMK untuk MK yang Anda ampu.'], 403);
            }
        }

        $cpmk = Cpmk::updateOrCreate(
            ['mata_kuliah_id' => $request->mata_kuliah_id, 'kode_cpmk' => $request->kode_cpmk],
            [
                'cpl_id' => $request->cpl_id,
                'deskripsi' => $request->deskripsi,
                'bobot_persentase' => $request->bobot_persentase ?? 0
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'CPMK berhasil disimpan',
            'data' => $cpmk
        ]);
    }

    // --- Komponen Penilaian Kelas OBE ---
    public function getKelasKomponen(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks.cpl'])->findOrFail($kelasId);
        $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
            ->where('kelas_id', $kelasId)
            ->orderBy('urutan')
            ->get();

        // Auto-sync jika komponen masih kosong dan mata kuliah sudah memiliki CPMK
        if ($komponen->isEmpty() && $kelas->mataKuliah && $kelas->mataKuliah->cpmks->isNotEmpty()) {
            $urutan = 1;
            foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                KomponenPenilaian::create([
                    'kelas_id' => $kelasId,
                    'cpmk_id' => $cpmk->id,
                    'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                    'teknik_penilaian' => 'tugas',
                    'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : 25,
                    'urutan' => $urutan++,
                    'is_aktif' => true,
                ]);
            }
            $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
                ->where('kelas_id', $kelasId)
                ->orderBy('urutan')
                ->get();
        }

        $totalBobot = $komponen->sum('bobot');

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => $kelas,
                'cpmk_options' => $kelas->mataKuliah->cpmks,
                'komponen' => $komponen,
                'total_bobot' => $totalBobot,
                'is_valid_100' => round($totalBobot, 2) === 100.00
            ]
        ]);
    }

    public function syncKelasKomponenFromObe(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks'])->findOrFail($kelasId);
        
        if (!$kelas->mataKuliah || $kelas->mataKuliah->cpmks->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mata kuliah belum memiliki data CPMK master OBE. Silakan konfigurasikan CPMK terlebih dahulu di menu OBE.'
            ], 422);
        }

        // Jangan hapus komponen yang sudah memiliki nilai mahasiswa
        $komponenIds = KomponenPenilaian::where('kelas_id', $kelasId)->pluck('id');
        $sudahDinilai = NilaiKomponenMahasiswa::whereIn('komponen_penilaian_id', $komponenIds)->exists();
        if ($sudahDinilai) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sinkronisasi dibatalkan: komponen kelas ini sudah memiliki nilai mahasiswa. Hapus/reset nilai terlebih dahulu atau kelola komponen secara manual agar nilai tidak hilang.'
            ], 422);
        }

        // Hapus komponen eksisting yang belum ada nilai
        KomponenPenilaian::where('kelas_id', $kelasId)->delete();

        $urutan = 1;
        foreach ($kelas->mataKuliah->cpmks as $cpmk) {
            KomponenPenilaian::create([
                'kelas_id' => $kelasId,
                'cpmk_id' => $cpmk->id,
                'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                'teknik_penilaian' => 'tugas',
                'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : round(100 / count($kelas->mataKuliah->cpmks), 2),
                'urutan' => $urutan++,
                'is_aktif' => true,
            ]);
        }

        $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
            ->where('kelas_id', $kelasId)
            ->orderBy('urutan')
            ->get();

        $totalBobot = $komponen->sum('bobot');

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen asesmen kelas berhasil disinkronkan dari Master CPMK OBE mata kuliah.',
            'data' => [
                'komponen' => $komponen,
                'total_bobot' => $totalBobot,
                'is_valid_100' => round($totalBobot, 2) === 100.00
            ]
        ]);
    }

    public function storeKelasKomponen(StoreKelasKomponenRequest $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';

        if ($mode === 'full_obe') {
            if ($request->filled('id')) {
                $cpmk = Cpmk::findOrFail($request->id);
                $cpmk->update([
                    'kode_cpmk' => $request->nama_komponen,
                    'bobot_persentase' => $request->bobot,
                ]);
            } else {
                $count = Cpmk::where('mata_kuliah_id', $kelas->mata_kuliah_id)->count();
                $cpmk = Cpmk::create([
                    'mata_kuliah_id' => $kelas->mata_kuliah_id,
                    'kode_cpmk' => $request->nama_komponen ?: ('CPMK-' . ($count + 1)),
                    'deskripsi' => $request->nama_komponen,
                    'bobot_persentase' => $request->bobot,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'CPMK penilaian OBE berhasil disimpan',
                'data' => $cpmk
            ]);
        }

        if ($request->filled('id')) {
            $comp = KomponenPenilaian::findOrFail($request->id);
            $comp->update($request->only(['nama_komponen', 'bobot', 'teknik_penilaian', 'cpmk_id', 'sub_cpmk_id']));
        } else {
            $maxUrutan = KomponenPenilaian::where('kelas_id', $kelasId)->max('urutan') ?? 0;
            $comp = KomponenPenilaian::create([
                'kelas_id' => $kelasId,
                'cpmk_id' => $request->cpmk_id,
                'sub_cpmk_id' => $request->sub_cpmk_id,
                'nama_komponen' => $request->nama_komponen,
                'teknik_penilaian' => $request->teknik_penilaian,
                'bobot' => $request->bobot,
                'urutan' => $maxUrutan + 1,
                'is_aktif' => true,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen penilaian berhasil disimpan',
            'data' => $comp
        ]);
    }

    public function deleteKelasKomponen($id)
    {
        $comp = KomponenPenilaian::find($id);
        if ($comp) {
            $comp->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Komponen penilaian berhasil dihapus'
            ]);
        }

        // If not in KomponenPenilaian, check if it's a CPMK (Pure OBE mode)
        $cpmk = Cpmk::find($id);
        if ($cpmk) {
            $cpmk->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'CPMK penilaian berhasil dihapus'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Komponen/CPMK penilaian tidak ditemukan'
        ], 404);
    }

    // --- Matriks Penilaian OBE Kelas & Rekap Capaian ---
    public function getKelasNilaiObe(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks.cpl', 'programStudi', 'programStudis', 'dosenPengampu.dosen', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';

        // Dosen murni hanya boleh membuka matriks kelas yang diampunya
        $reqUser = $request->user();
        $reqPriv = $reqUser && ($reqUser->isSuperAdmin() || $reqUser->hasRole('admin') || $reqUser->hasRole('kaprodi') || $reqUser->hasRole('wakil_prodi'));
        if ($reqUser && !$reqPriv) {
            $reqDosen = \App\Models\Siakad\Dosen::where('user_id', $reqUser->id)->first();
            $mengampu = $reqDosen && $kelas->dosenPengampu->contains(fn($dp) => (int) $dp->dosen_id === (int) $reqDosen->id);
            if (!$mengampu) {
                return response()->json(['status' => 'error', 'message' => 'Anda hanya dapat membuka kelas yang Anda ampu.'], 403);
            }
        }
        
        // 1. Define the components list based on the active mode
        if ($mode === 'full_obe') {
            // In Full OBE, components are the CPMKs themselves
            $komponenList = $kelas->mataKuliah->cpmks->map(function ($cpmk) {
                return (object) [
                    'id' => $cpmk->id,
                    'nama_komponen' => $cpmk->kode_cpmk,
                    'bobot' => $cpmk->bobot_persentase,
                    'cpmk_id' => $cpmk->id,
                ];
            });
        } else {
            // For Semi-OBE and Conventional, use traditional components
            $komponenList = KomponenPenilaian::with('cpmk')->where('kelas_id', $kelasId)->orderBy('urutan')->get();
            // Auto sync if empty
            if ($komponenList->isEmpty() && $kelas->mataKuliah && $kelas->mataKuliah->cpmks->isNotEmpty()) {
                $urutan = 1;
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    KomponenPenilaian::create([
                        'kelas_id' => $kelasId,
                        'cpmk_id' => $cpmk->id,
                        'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                        'teknik_penilaian' => 'tugas',
                        'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : 25,
                        'urutan' => $urutan++,
                        'is_aktif' => true,
                    ]);
                }
                $komponenList = KomponenPenilaian::with('cpmk')->where('kelas_id', $kelasId)->orderBy('urutan')->get();
            }
        }

        // Ambil semua mahasiswa yang terdaftar di kelas ini via KRS
        $krsDetails = KrsDetail::with([
            'krs.mahasiswa.programStudi',
            'nilai',
            'nilaiKomponens.komponenPenilaian',
            'ketercapaianCpmks.cpmk'
        ])
        ->where('kelas_id', $kelasId)
        ->where('status', 'aktif')
        ->get();

        $peserta = $krsDetails->map(function ($kd) use ($kelas, $komponenList, $mode) {
            $mhs = $kd->krs?->mahasiswa;
            $totalAkhir = 0;
            $scores = [];
            $cpmkAttainment = [];

            if ($mode === 'full_obe') {
                $cpmkRecords = $kd->ketercapaianCpmks->keyBy('cpmk_id');
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $rec = $cpmkRecords->get($cpmk->id);
                    $skor = $rec ? (float)$rec->skor_ketercapaian : 0.0;
                    $bobot = (float)$cpmk->bobot_persentase;
                    $kontribusi = ($skor * $bobot) / 100;
                    $totalAkhir += $kontribusi;

                    $scores[$cpmk->id] = [
                        'komponen_id' => $cpmk->id,
                        'nama_komponen' => $cpmk->kode_cpmk,
                        'bobot' => $bobot,
                        'nilai_angka' => $skor,
                        'kontribusi' => round($kontribusi, 2),
                        'cpmk_id' => $cpmk->id,
                    ];
                }
            } else {
                // semi_obe or konvensional
                $nilaiRecords = $kd->nilaiKomponens->keyBy('komponen_penilaian_id');
                foreach ($komponenList as $comp) {
                    $rec = $nilaiRecords->get($comp->id);
                    $skor = $rec ? (float)$rec->nilai_angka : 0.0;
                    $bobot = (float)$comp->bobot;
                    $kontribusi = ($skor * $bobot) / 100;
                    $totalAkhir += $kontribusi;

                    $scores[$comp->id] = [
                        'komponen_id' => $comp->id,
                        'nama_komponen' => $comp->nama_komponen,
                        'bobot' => $bobot,
                        'nilai_angka' => $skor,
                        'kontribusi' => round($kontribusi, 2),
                        'cpmk_id' => $comp->cpmk_id,
                    ];
                }
            }

            // 2. Map CPMK attainment depending on the mode
            if ($mode === 'full_obe') {
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $skor = isset($scores[$cpmk->id]) ? $scores[$cpmk->id]['nilai_angka'] : 0.0;
                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => $skor,
                        'is_tercapai' => $skor >= 65.0,
                    ];
                }
            } elseif ($mode === 'semi_obe') {
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                    $totalCompWeight = $relatedComps->sum('bobot');
                    $cpmkScore = 0;
                    if ($totalCompWeight > 0) {
                        $weightedSum = 0;
                        foreach ($relatedComps as $rc) {
                            $s = $scores[$rc->id]['nilai_angka'] ?? 0;
                            $weightedSum += ($s * (float)$rc->bobot);
                        }
                        $cpmkScore = round($weightedSum / $totalCompWeight, 2);
                    } else {
                        $cpmkScore = round($totalAkhir, 2);
                    }

                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => $cpmkScore,
                        'is_tercapai' => $cpmkScore >= 65.0,
                    ];
                }
            } else {
                // konvensional: CPMK attainment is empty/not measured
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => 0.0,
                        'is_tercapai' => true,
                    ];
                }
            }

            // Nilai Huruf & Mutu — sumber tunggal: master skala nilai (fallback baku bila kosong)
            [$huruf, $mutu] = $this->konversiHurufMutu((float) $totalAkhir, $kelas->mataKuliah?->kurikulum?->program_studi_id);

            return [
                'krs_detail_id' => $kd->id,
                'mahasiswa' => $mhs,
                'scores' => $scores,
                'cpmk_attainment' => $cpmkAttainment,
                'nilai_akhir' => round($totalAkhir, 2),
                'nilai_huruf' => $huruf,
                'bobot_mutu' => $mutu,
                'is_final' => (bool) ($kd->nilai?->is_final ?? false),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => $kelas,
                'komponen' => $komponenList,
                'cpmks' => $mode === 'konvensional' ? [] : $kelas->mataKuliah->cpmks,
                'peserta' => $peserta,
                'mode_penilaian' => $mode,
                'total_bobot_komponen' => round((float) $komponenList->sum('bobot'), 2),
                'total_bobot_cpmk' => round((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase'), 2),
                'is_valid_100' => $mode === 'full_obe'
                    ? abs((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase') - 100.0) < 0.01
                    : abs((float) $komponenList->sum('bobot') - 100.0) < 0.01,
                'kelayakan' => $this->kelayakanInputNilai($kelas, $mode, $komponenList),
                'skala_nilai' => SkalaNilai::where('is_active', true)->orderBy('bobot_indeks', 'desc')->get(),
            ]
        ]);
    }

    /**
     * Info kelayakan input nilai kelas: RPS terisi + bobot 100%.
     *
     * @return array{boleh:bool, pesan:string|null, rps:array, bobot:array}
     */
    private function kelayakanInputNilai(Kelas $kelas, string $mode, $komponenList): array
    {
        $mkId = $kelas->mata_kuliah_id;
        $rps = \App\Models\Siakad\Rps::where('mata_kuliah_id', $mkId)
            ->withCount('mingguan')
            ->orderByDesc('id')
            ->first();

        $rpsInfo = [
            'ada' => (bool) $rps,
            'jumlah_pertemuan' => $rps ? (int) $rps->mingguan_count : 0,
            'status' => $rps?->status,
            'terisi' => (bool) $rps && (int) $rps->mingguan_count > 0,
        ];

        if (!$rpsInfo['terisi']) {
            return [
                'boleh' => false,
                'pesan' => 'Pengisian nilai dikunci: RPS mata kuliah ini belum diisi (minimal 1 dari 16 rencana pertemuan mingguan). Lengkapi RPS terlebih dahulu di menu Perkuliahan → Kelola RPS.',
                'rps' => $rpsInfo,
                'bobot' => null,
            ];
        }

        if ($mode === 'full_obe') {
            $total = round((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase'), 2);
            $ok = abs($total - 100.0) < 0.01;
            return [
                'boleh' => $ok,
                'pesan' => $ok ? null : "Pengisian nilai dikunci: total bobot CPMK mata kuliah ini belum genap 100% (saat ini: {$total}%). Lengkapi pemetaan CPMK di menu OBE.",
                'rps' => $rpsInfo,
                'bobot' => ['tipe' => 'cpmk', 'total' => $total, 'valid_100' => $ok],
            ];
        }

        $total = round((float) $komponenList->sum('bobot'), 2);
        $ok = abs($total - 100.0) < 0.01;
        return [
            'boleh' => $ok,
            'pesan' => $ok ? null : "Pengisian nilai dikunci: total bobot komponen asesmen kelas ini belum genap 100% (saat ini: {$total}%). Sesuaikan komponen penilaian kelas terlebih dahulu.",
            'rps' => $rpsInfo,
            'bobot' => ['tipe' => 'komponen', 'total' => $total, 'valid_100' => $ok],
        ];
    }

    /**
     * Konversi nilai angka ke huruf & mutu via master skala nilai.
     * Fallback ke rentang baku bila master belum dikonfigurasi.
     *
     * @return array{0:string,1:float}
     */
    private function konversiHurufMutu(float $nilaiAkhir, ?int $prodiId = null): array
    {
        $skala = SkalaNilai::konversiNilai($nilaiAkhir, $prodiId);
        if ($skala) {
            return [$skala->nilai_huruf, (float) $skala->bobot_indeks];
        }

        $huruf = 'E';
        $mutu = 0.00;
        if ($nilaiAkhir >= 85) { $huruf = 'A'; $mutu = 4.00; }
        elseif ($nilaiAkhir >= 80) { $huruf = 'A-'; $mutu = 3.75; }
        elseif ($nilaiAkhir >= 75) { $huruf = 'B+'; $mutu = 3.25; }
        elseif ($nilaiAkhir >= 70) { $huruf = 'B'; $mutu = 3.00; }
        elseif ($nilaiAkhir >= 65) { $huruf = 'B-'; $mutu = 2.75; }
        elseif ($nilaiAkhir >= 60) { $huruf = 'C+'; $mutu = 2.25; }
        elseif ($nilaiAkhir >= 55) { $huruf = 'C'; $mutu = 2.00; }
        elseif ($nilaiAkhir >= 40) { $huruf = 'D'; $mutu = 1.00; }

        return [$huruf, $mutu];
    }

    public function saveBulkNilaiObe(Request $request, $kelasId)
    {
        $request->validate([
            'is_final' => 'nullable|boolean',
            'grades' => 'required|array',
            'grades.*.krs_detail_id' => 'required|exists:siakad_krs_detail,id',
            'grades.*.scores' => 'required|array',
        ]);

        $kelas = Kelas::with(['mataKuliah.cpmks', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        $komponenList = KomponenPenilaian::where('kelas_id', $kelasId)->get();
        $isFinalInput = $request->boolean('is_final', false);

        // Kunci prasyarat: RPS terisi + bobot 100%
        $kelayakan = $this->kelayakanInputNilai($kelas, $mode, $komponenList);
        if (!$kelayakan['boleh']) {
            return response()->json([
                'status' => 'error',
                'message' => $kelayakan['pesan'],
                'data' => ['kelayakan' => $kelayakan],
            ], 422);
        }

        // Validasi Jadwal Periode Pengisian Nilai (Kecuali jika Admin)
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $ta = $kelas->tahunAkademik;
            if ($ta && $ta->input_nilai_selesai && now()->greaterThan(\Carbon\Carbon::parse($ta->input_nilai_selesai))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batas waktu pengisian nilai untuk periode akademik ini telah ditutup (' . \Carbon\Carbon::parse($ta->input_nilai_selesai)->translatedFormat('d F Y') . '). Hubungi Bagian BAAK untuk dispensasi pengisian nilai.'
                ], 403);
            }
        }

        DB::transaction(function () use ($request, $kelas, $komponenList, $isFinalInput, $mode) {
            foreach ($request->grades as $g) {
                $kd = KrsDetail::with(['krs', 'kelas.mataKuliah.cpmks'])->findOrFail($g['krs_detail_id']);
                $scoresInput = $g['scores'];
                $totalAkhir = 0;

                if ($mode === 'full_obe') {
                    foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                        $val = isset($scoresInput[$cpmk->id]) ? (float)$scoresInput[$cpmk->id] : 0.0;
                        $val = min(100, max(0, $val));

                        KetercapaianCpmkMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'cpmk_id' => $cpmk->id,
                            ],
                            [
                                'skor_ketercapaian' => $val,
                                'status_ketercapaian' => $val >= 65.0 ? 'tercapai' : 'belum_tercapai',
                            ]
                        );

                        $totalAkhir += ($val * (float)$cpmk->bobot_persentase) / 100;
                    }
                } else {
                    // semi_obe or konvensional
                    foreach ($komponenList as $comp) {
                        $val = isset($scoresInput[$comp->id]) ? (float)$scoresInput[$comp->id] : 0.0;
                        $val = min(100, max(0, $val));

                        NilaiKomponenMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'komponen_penilaian_id' => $comp->id,
                            ],
                            [
                                'nilai_angka' => $val,
                                'diinput_oleh' => $request->user()?->id,
                            ]
                        );

                        $totalAkhir += ($val * (float)$comp->bobot) / 100;
                    }

                    // For Semi-OBE, calculate and sync CPMK attainment based on components
                    if ($mode === 'semi_obe') {
                        foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                            $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                            $totalW = $relatedComps->sum('bobot');
                            $cpmkScore = 0;
                            if ($totalW > 0) {
                                $wSum = 0;
                                foreach ($relatedComps as $rc) {
                                    $s = isset($scoresInput[$rc->id]) ? (float)$scoresInput[$rc->id] : 0.0;
                                    $wSum += ($s * (float)$rc->bobot);
                                }
                                $cpmkScore = round($wSum / $totalW, 2);
                            } else {
                                $cpmkScore = round($totalAkhir, 2);
                            }

                            KetercapaianCpmkMahasiswa::updateOrCreate(
                                [
                                    'krs_detail_id' => $kd->id,
                                    'cpmk_id' => $cpmk->id,
                                ],
                                [
                                    'skor_ketercapaian' => $cpmkScore,
                                    'status_ketercapaian' => $cpmkScore >= 65.0 ? 'tercapai' : 'belum_tercapai',
                                ]
                            );
                        }
                    }
                }

                // Sync ke siakad_nilai_mahasiswa — skala dari master
                [$hurufBulk, $mutuBulk] = $this->konversiHurufMutu((float) $totalAkhir, $kelas->mataKuliah?->kurikulum?->program_studi_id);

                NilaiMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id],
                    [
                        'nilai_akhir' => round($totalAkhir, 2),
                        'nilai_huruf' => $hurufBulk,
                        'bobot_mutu' => $mutuBulk,
                        'is_final' => $isFinalInput,
                        'diinput_oleh' => $request->user()?->id,
                    ]
                );

                if ($isFinalInput && $kd->krs) {
                    $this->akademikService->hitungKhsDanIpk(
                        $kd->krs->mahasiswa_id,
                        $kd->krs->tahun_akademik_id
                    );
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => $isFinalInput 
                ? 'Seluruh nilai mahasiswa berhasil disimpan dan dipublikasikan (Final).'
                : 'Nilai mahasiswa berhasil disimpan sebagai Draft.',
        ]);
    }

    public function saveKelasNilaiObe(Request $request, $kelasId)
    {
        $request->validate([
            'krs_detail_id' => 'required|exists:siakad_krs_detail,id',
            'scores' => 'required|array',
            'is_final' => 'nullable|boolean',
        ]);

        $kd = KrsDetail::with(['krs.mahasiswa', 'kelas.mataKuliah.cpmks', 'kelas.tahunAkademik'])->findOrFail($request->krs_detail_id);
        $mode = $kd->kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        $komponenList = KomponenPenilaian::where('kelas_id', $kelasId)->get();
        $isFinalInput = $request->boolean('is_final', false);

        // Kunci prasyarat: RPS terisi + bobot 100%
        $kelayakan = $this->kelayakanInputNilai($kd->kelas, $mode, $komponenList);
        if (!$kelayakan['boleh']) {
            return response()->json([
                'status' => 'error',
                'message' => $kelayakan['pesan'],
                'data' => ['kelayakan' => $kelayakan],
            ], 422);
        }

        // Validasi Jadwal Periode Pengisian Nilai (Kecuali jika Admin)
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $ta = $kd->kelas->tahunAkademik;
            if ($ta && $ta->input_nilai_selesai && now()->greaterThan(\Carbon\Carbon::parse($ta->input_nilai_selesai))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batas waktu pengisian nilai untuk periode akademik ini telah ditutup (' . \Carbon\Carbon::parse($ta->input_nilai_selesai)->translatedFormat('d F Y') . '). Hubungi Bagian BAAK untuk dispensasi pengisian nilai.'
                ], 403);
            }
        }

        $totalAkhir = 0;
        $scoresInput = $request->scores;

        DB::transaction(function () use ($kd, $scoresInput, $request, $isFinalInput, $komponenList, $mode, &$totalAkhir) {
            if ($mode === 'full_obe') {
                foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                    $val = isset($scoresInput[$cpmk->id]) ? (float)$scoresInput[$cpmk->id] : 0.0;
                    $val = min(100, max(0, $val));

                    KetercapaianCpmkMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id' => $kd->id,
                            'cpmk_id' => $cpmk->id,
                        ],
                        [
                            'skor_ketercapaian' => $val,
                            'status_ketercapaian' => $val >= 65.0 ? 'tercapai' : 'belum_tercapai',
                        ]
                    );

                    $totalAkhir += ($val * (float)$cpmk->bobot_persentase) / 100;
                }
            } else {
                // semi_obe or konvensional
                foreach ($komponenList as $comp) {
                    $val = isset($scoresInput[$comp->id]) ? (float)$scoresInput[$comp->id] : 0.0;
                    $val = min(100, max(0, $val));

                    NilaiKomponenMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id' => $kd->id,
                            'komponen_penilaian_id' => $comp->id,
                        ],
                        [
                            'nilai_angka' => $val,
                            'diinput_oleh' => $request->user()?->id,
                        ]
                    );

                    $totalAkhir += ($val * (float)$comp->bobot) / 100;
                }

                if ($mode === 'semi_obe') {
                    foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                        $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                        $totalW = $relatedComps->sum('bobot');
                        $cpmkScore = 0;
                        if ($totalW > 0) {
                            $wSum = 0;
                            foreach ($relatedComps as $rc) {
                                $s = isset($scoresInput[$rc->id]) ? (float)$scoresInput[$rc->id] : 0.0;
                                $wSum += ($s * (float)$rc->bobot);
                            }
                            $cpmkScore = round($wSum / $totalW, 2);
                        } else {
                            $cpmkScore = round($totalAkhir, 2);
                        }

                        KetercapaianCpmkMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'cpmk_id' => $cpmk->id,
                            ],
                            [
                                'skor_ketercapaian' => $cpmkScore,
                                'status_ketercapaian' => $cpmkScore >= 65.0 ? 'tercapai' : 'belum_tercapai',
                            ]
                        );
                    }
                }
            }

            // Sync ke siakad_nilai_mahasiswa — skala dari master
            [$hurufSingle, $mutuSingle] = $this->konversiHurufMutu((float) $totalAkhir, $kd->kelas?->mataKuliah?->kurikulum?->program_studi_id);

            NilaiMahasiswa::updateOrCreate(
                ['krs_detail_id' => $kd->id],
                [
                    'nilai_akhir' => round($totalAkhir, 2),
                    'nilai_huruf' => $hurufSingle,
                    'bobot_mutu' => $mutuSingle,
                    'is_final' => $isFinalInput,
                    'diinput_oleh' => $request->user()?->id,
                ]
            );

            if ($isFinalInput && $kd->krs) {
                $this->akademikService->hitungKhsDanIpk(
                    $kd->krs->mahasiswa_id,
                    $kd->krs->tahun_akademik_id
                );
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai OBE dan Ketercapaian CPMK berhasil diperbarui.',
            'data' => [
                'nilai_akhir' => round($totalAkhir, 2),
            ]
        ]);
    }

    // --- RPS & Alur Approval Prodi (Menampilkan Semua Distribusi Mengajar Prodi) ---
    public function listRps(Request $request)
    {
        $prodiIds = $this->resolveObeProdiId($request);

        // Query basis: Distribusi Mengajar prodi aktif
        $distQuery = \App\Models\Siakad\DistribusiMengajar::with([
            'mataKuliah.kurikulum.programStudi',
            'mataKuliah.rumpunMataKuliah',
            'dosenKoordinator',
            'kurikulum',
            'tahunAkademik'
        ])->whereHas('mataKuliah.kurikulum', fn($q) => $q->whereIn('program_studi_id', $prodiIds));

        if ($request->filled('kurikulum_id')) {
            $distQuery->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('mata_kuliah_id')) {
            $distQuery->where('mata_kuliah_id', $request->mata_kuliah_id);
        }

        if ($request->filled('semester')) {
            $distQuery->where('semester', $request->semester);
        }

        if ($request->filled('dosen_id')) {
            $dosenId = (int) $request->dosen_id;
            $distQuery->where(function ($q) use ($dosenId) {
                $q->where('dosen_koordinator_id', $dosenId)
                  ->orWhereJsonContains('dosen_anggota_ids', $dosenId);
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $distQuery->where(function ($q) use ($s) {
                $q->whereHas('mataKuliah', fn($mq) => $mq->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"))
                  ->orWhereHas('dosenKoordinator', fn($dq) => $dq->where('nama_lengkap', 'like', "%{$s}%"));
            });
        }

        $allowedSort = ['created_at', 'id', 'semester'];
        $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'semester';
        $distQuery->orderBy($sortBy, $request->sort_order === 'desc' ? 'desc' : 'asc');

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $distQuery->paginate($perPage);

            $items = $this->transformDistribusiToRpsRow($data->items());

            return response()->json([
                'status' => 'success',
                'message' => 'Daftar dokumen RPS berhasil dimuat',
                'data' => $items,
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        $distList = $distQuery->get();
        $items = $this->transformDistribusiToRpsRow($distList->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dokumen RPS berhasil dimuat',
            'data' => $items
        ]);
    }

    private function transformDistribusiToRpsRow(array $distribusiItems): array
    {
        $mkIds = collect($distribusiItems)->map(fn($r) => $r->mata_kuliah_id ?? $r['mata_kuliah_id'] ?? null)->filter()->unique()->values();

        $rpsRows = $mkIds->isNotEmpty()
            ? \App\Models\Siakad\Rps::with(['dosenPengembang', 'koordinatorRmk', 'kaprodi'])->whereIn('mata_kuliah_id', $mkIds)->get()->keyBy('mata_kuliah_id')
            : collect();

        $allKelasIds = collect($distribusiItems)->pluck('kelas_ids')->flatten()->map(fn($v) => (int) $v)->filter()->unique()->values();

        $masterKelasMap = $allKelasIds->isNotEmpty()
            ? \App\Models\Siakad\MasterKelas::whereIn('id', $allKelasIds)->get()->keyBy('id')
            : collect();

        $allAnggotaIds = collect($distribusiItems)->pluck('dosen_anggota_ids')->flatten()
            ->concat($rpsRows->pluck('dosen_anggota_ids')->flatten())
            ->map(fn($v) => (int) $v)->filter()->unique()->values();

        $dosenMap = $allAnggotaIds->isNotEmpty()
            ? \App\Models\Siakad\Dosen::whereIn('id', $allAnggotaIds)->get()->keyBy('id')
            : collect();

        return array_map(function ($item) use ($rpsRows, $masterKelasMap, $dosenMap) {
            $dist = $item instanceof \Illuminate\Database\Eloquent\Model ? $item : (object) $item;
            $mkId = $dist->mata_kuliah_id;

            $rps = $rpsRows->get($mkId);
            $hasRps = (bool) $rps;

            $kIds = $dist->kelas_ids ?? [];
            $kIds = collect(is_array($kIds) ? $kIds : [])->map(fn($v) => (int) $v)->filter()->values();
            $kelasList = $kIds->map(fn($kid) => $masterKelasMap->get($kid)?->nama_kelas)->filter()->values()->all();

            $rawAnggotaIds = $rps->dosen_anggota_ids ?? $dist->dosen_anggota_ids ?? [];
            $rawAnggotaIds = collect(is_array($rawAnggotaIds) ? $rawAnggotaIds : [])->map(fn($v) => (int) $v)->filter()->values();
            $anggotas = $rawAnggotaIds->map(fn($aid) => $dosenMap->get($aid))->filter()->values()->all();

            return [
                'id' => $rps?->id ?? null,
                'has_rps' => $hasRps,
                'distribusi_id' => $dist->id,
                'mata_kuliah_id' => $mkId,
                'mata_kuliah' => $dist->mataKuliah,
                'kurikulum' => $dist->kurikulum ?? $dist->mataKuliah?->kurikulum,
                'semester' => $dist->semester ?? $dist->mataKuliah?->semester_anjuran ?? 1,
                'tahun_ajaran' => $rps?->tahun_ajaran ?? ($dist->tahunAkademik?->nama ?? null),
                'kode_rps' => $rps?->kode_rps ?? null,
                'dosen_bisa_edit' => $rps?->dosen_bisa_edit ?? true,
                'dosen_koordinator' => $rps?->koordinatorRmk ?? $dist->dosenKoordinator,
                'dosen_anggotas' => $anggotas,
                'kaprodi' => $rps?->kaprodi ?? null,
                'distribusi_kelas_list' => $kelasList,
                'distribusi_kelas_formatted' => !empty($kelasList) ? implode(',', $kelasList) : null,
                'created_at' => $rps?->created_at ?? $dist->created_at,
                'updated_at' => $rps?->updated_at ?? $dist->updated_at,
            ];
        }, $distribusiItems);
    }

    public function toggleDosenBisaEditRps(Request $request, int $id)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $rps = \App\Models\Siakad\Rps::with('mataKuliah.kurikulum')->findOrFail($id);

        $prodiId = (int) ($rps->mataKuliah?->kurikulum?->program_studi_id ?? 0);
        $user = $request->user();
        if ($user && !$user->canManageObeForProdi($prodiId)) {
            abort(403, 'Anda tidak memiliki hak akses mengubah pengaturan RPS prodi ini.');
        }

        $validated = $request->validate([
            'dosen_bisa_edit' => 'required|boolean',
        ]);

        $rps->update([
            'dosen_bisa_edit' => $validated['dosen_bisa_edit'],
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_rps',
                recordId: $rps->id,
                oldValues: ['dosen_bisa_edit' => !$validated['dosen_bisa_edit']],
                newValues: ['dosen_bisa_edit' => $validated['dosen_bisa_edit']],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log toggle dosen_bisa_edit RPS: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Hak akses edit dosen untuk RPS berhasil diperbarui',
            'data' => $rps,
        ]);
    }

    public function showRps($id)
    {
        $rps = \App\Models\Siakad\Rps::with([
            'mataKuliah.cpmks.subCpmks',
            'mataKuliah.cpmks.cpl',
            'mataKuliah.cpls',
            'mataKuliah.cpmkProdis.cpl',
            'mataKuliah.kurikulum.programStudi.fakultas',
            'dosenPengembang',
            'koordinatorRmk',
            'kaprodi',
            'mingguan'
        ])->findOrFail($id);

        $prodiId = (int) ($rps->mataKuliah?->kurikulum?->program_studi_id ?? 0);
        $user = request()->user();
        if ($user && !$user->canManageObeForProdi($prodiId)) {
            abort(403, 'Anda tidak memiliki hak akses melihat dokumen RPS program studi ini.');
        }

        // Kelas yang memakai RPS ini (MK sama) beserta jadwal & pengampu — read-only
        $kelasPemakai = \App\Models\Siakad\Kelas::with(['tahunAkademik', 'ruangan.gedung', 'programStudi', 'dosenPengampu.dosen'])
            ->where('mata_kuliah_id', $rps->mata_kuliah_id)
            ->orderByDesc('tahun_akademik_id')
            ->get(['id', 'mata_kuliah_id', 'tahun_akademik_id', 'program_studi_id', 'ruangan_id', 'kode_kelas', 'nama_kelas', 'hari', 'jam_mulai', 'jam_selesai', 'kapasitas', 'status']);

        // Resolve anggota RPS (JSON array of ID) menjadi objek Dosen agar label
        // dapat ditampilkan pada form Edit tanpa fetch tambahan dari FE.
        $anggotaIds = collect($rps->dosen_anggota_ids ?? [])
            ->map(fn($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();
        $dosenAnggotas = $anggotaIds->isNotEmpty()
            ? \App\Models\Siakad\Dosen::whereIn('id', $anggotaIds)->get()
            : collect();

        return response()->json([
            'status' => 'success',
            'data' => array_merge($rps->toArray(), [
                'kelas_pemakai' => $kelasPemakai,
                'dosen_anggotas' => $dosenAnggotas,
            ])
        ]);
    }

    public function storeRps(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'kode_rps' => 'nullable|string|max:100',
            'tahun_ajaran' => 'nullable|string',
            'semester' => 'nullable|integer',
            'tanggal_penyusunan' => 'nullable|date',
            'dosen_pengembang_id' => 'nullable|exists:siakad_dosen,id',
            'dosen_anggota_ids' => 'nullable|array',
            'koordinator_rmk_id' => 'nullable|exists:siakad_dosen,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'dosen_bisa_edit' => 'nullable|boolean',
            'deskripsi_singkat' => 'nullable|string',
            'bahan_kajian_mk' => 'nullable|string',
            'mata_kuliah_syarat' => 'nullable|string|max:255',
            'jenis_pembelajaran' => 'nullable|string|max:100',
            'pustaka_utama' => 'nullable|string',
            'pustaka_pendukung' => 'nullable|string',
            'mingguan' => 'nullable|array',
        ]);

        $mk = \App\Models\Siakad\MataKuliah::with('kurikulum')->findOrFail($request->mata_kuliah_id);
        $prodiId = (int) ($mk->kurikulum?->program_studi_id ?? 0);
        $user = $request->user();
        if ($user && !$user->canManageObeForProdi($prodiId)) {
            abort(403, 'Anda tidak memiliki hak akses menyimpan dokumen RPS untuk program studi ini.');
        }

        $rps = \App\Models\Siakad\Rps::updateOrCreate(
            ['id' => $request->id],
            $request->except(['mingguan'])
        );

        if ($request->has('mingguan') && is_array($request->mingguan)) {
            foreach ($request->mingguan as $m) {
                if (isset($m['minggu_ke'])) {
                    \App\Models\Siakad\RpsMingguan::updateOrCreate(
                        [
                            'rps_id' => $rps->id,
                            'minggu_ke' => $m['minggu_ke'],
                        ],
                        [
                            'kemampuan_akhir' => $m['kemampuan_akhir'] ?? "Sub-CPMK {$m['minggu_ke']}",
                            'bahan_kajian' => $m['bahan_kajian'] ?? "Bahan Kajian Minggu {$m['minggu_ke']}",
                            'bentuk_metode' => $m['bentuk_metode'] ?? 'Kuliah, Diskusi, & Problem-Based Learning',
                            'estimasi_waktu' => $m['estimasi_waktu'] ?? '2 x 50 Menit',
                            'pengalaman_belajar' => $m['pengalaman_belajar'] ?? 'Menganalisis studi kasus dan tugas terstruktur.',
                            'indikator_penilaian' => $m['indikator_penilaian'] ?? 'Ketepatan analisis dan pemahaman materi.',
                            'bobot_penilaian' => isset($m['bobot_penilaian']) ? (float)$m['bobot_penilaian'] : ($m['minggu_ke'] == 8 ? 25.0 : ($m['minggu_ke'] == 16 ? 30.0 : 3.0)),
                        ]
                    );
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen RPS beserta 16 rencana pertemuan mingguan berhasil disimpan',
            'data' => $rps->load(['mingguan', 'dosenPengembang', 'kaprodi'])
        ]);
    }

    public function destroyRps(Request $request, int $id)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $rps = \App\Models\Siakad\Rps::with('mataKuliah.kurikulum')->findOrFail($id);
        $prodiId = (int) ($rps->mataKuliah?->kurikulum?->program_studi_id ?? 0);
        $user = $request->user();
        if ($user && !$user->canManageObeForProdi($prodiId)) {
            abort(403, 'Anda tidak memiliki hak akses menghapus dokumen RPS prodi ini.');
        }

        $old = $rps->toArray();
        $rps->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_rps',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log delete RPS: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen RPS berhasil dihapus',
            'data' => null,
        ]);
    }

    public function getMahasiswaPortofolioObe(Request $request, $mahasiswaId = null)
    {
        $user = $request->user();
        $mahasiswa = null;

        // 1. Jika diberikan ID numerik
        if ($mahasiswaId && is_numeric($mahasiswaId) && (int)$mahasiswaId > 0) {
            $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->find($mahasiswaId);

            // Dosen non-admin: hanya bimbingan / peserta kelasnya
            $privPorto = $user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));
            if ($mahasiswa && $user && !$privPorto) {
                $dosen = \App\Models\Siakad\Dosen::where('user_id', $user->id)->first();
                if ($dosen) {
                    $isAdvisee = (int) $mahasiswa->dosen_wali_id === (int) $dosen->id;
                    $isPeserta = \App\Models\Siakad\KrsDetail::whereHas('krs', fn($q) => $q->where('mahasiswa_id', $mahasiswa->id))
                        ->whereHas('kelas.dosenPengampu', fn($q) => $q->where('dosen_id', $dosen->id))
                        ->exists();
                    if (!$isAdvisee && !$isPeserta) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Anda hanya dapat melihat mahasiswa bimbingan atau peserta kelas Anda.',
                            'data' => null,
                        ], 403);
                    }
                }
            }
        }

        // 2. Jika dipanggil oleh mahasiswa yang sedang login
        if (!$mahasiswa && $user) {
            $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('user_id', $user->id)->first();
            if (!$mahasiswa && !empty($user->username)) {
                $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('nim', $user->username)->first();
            }
            if (!$mahasiswa && !empty($user->email)) {
                $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('email', $user->email)->first();
            }
        }

        // 3. Admin/dosen wajib menyertakan ID spesifik, tanpa fallback ke mahasiswa pertama
        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan. Sertakan mahasiswa_id yang valid.',
                'data' => null,
            ], 404);
        }

        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa belum terdaftar di sistem.',
                'data' => null,
            ], 404);
        }
        
        // Ambil semua CPL yang terdefinisi di program studi mahasiswa
        $cpls = Cpl::where('program_studi_id', $mahasiswa->program_studi_id)
            ->with(['cpmks.mataKuliah'])
            ->get();

        if ($cpls->isEmpty()) {
            $cpls = Cpl::with(['cpmks.mataKuliah'])->get();
        }

        // Ambil data ketercapaian CPMK dari KRS mahasiswa
        $krsDetails = KrsDetail::whereHas('krs', fn($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->with(['ketercapaianCpmk.cpmk.cpl', 'kelas.mataKuliah', 'krs.tahunAkademik', 'nilai'])
            ->get();

        $cplSummary = [];
        $kategoriScores = [
            'sikap' => [],
            'pengetahuan' => [],
            'keterampilan_umum' => [],
            'keterampilan_khusus' => [],
        ];

        foreach ($cpls as $cpl) {
            $cpmkIds = $cpl->cpmks ? $cpl->cpmks->pluck('id')->toArray() : [];
            $attainedList = [];

            foreach ($krsDetails as $kd) {
                if ($kd->ketercapaianCpmk) {
                    foreach ($kd->ketercapaianCpmk as $kc) {
                        if (in_array($kc->cpmk_id, $cpmkIds)) {
                            $attainedList[] = (float) $kc->skor_ketercapaian;
                        }
                    }
                }
            }

            $hasAssessment = count($attainedList) > 0;
            $avgScore = $hasAssessment ? round(array_sum($attainedList) / count($attainedList), 1) : 0.0;

            $cplSummary[] = [
                'cpl_id' => $cpl->id,
                'kode_cpl' => $cpl->kode_cpl,
                'kategori' => $cpl->kategori,
                'deskripsi' => $cpl->deskripsi,
                'skor_rata_rata' => $avgScore,
                'status' => $hasAssessment ? ($avgScore >= 65.0 ? 'Memenuhi Standar' : 'Belum Memenuhi') : 'Belum Dinilai (0%)',
                'total_mata_kuliah_diukur' => $hasAssessment ? count($attainedList) : 0,
            ];

            if (isset($kategoriScores[$cpl->kategori])) {
                $kategoriScores[$cpl->kategori][] = $avgScore;
            }
        }

        $radarKategori = [];
        foreach ($kategoriScores as $kat => $scores) {
            $radarKategori[$kat] = count($scores) > 0 
                ? round(array_sum($scores) / count($scores), 1)
                : 0.0;
        }

        // Rincian per-MK per-semester: MK apa saja yang diambil + capaian CPMK-nya
        $mkDetails = $krsDetails->map(function ($kd) {
            $mk = $kd->kelas?->mataKuliah;
            $cpmkScores = ($kd->ketercapaianCpmk ?? collect())->map(fn($kc) => [
                'cpmk_id' => $kc->cpmk_id,
                'kode_cpmk' => $kc->cpmk?->kode_cpmk,
                'skor' => (float) $kc->skor_ketercapaian,
                'is_tercapai' => ($kc->status_ketercapaian ?? '') === 'tercapai' || (float) $kc->skor_ketercapaian >= 65.0,
            ])->values();
            return [
                'krs_detail_id' => $kd->id,
                'tahun_akademik_id' => $kd->krs?->tahun_akademik_id,
                'semester_label' => $kd->krs?->tahunAkademik?->nama ?? 'Semester',
                'kode_mk' => $mk?->kode_mk ?? '-',
                'nama_mk' => $mk?->nama ?? 'Mata Kuliah',
                'sks' => $mk?->total_sks ?? 0,
                'nilai_akhir' => $kd->nilai ? (float) $kd->nilai->nilai_akhir : null,
                'nilai_huruf' => $kd->nilai?->nilai_huruf,
                'is_final' => (bool) ($kd->nilai?->is_final ?? false),
                'cpmk_scores' => $cpmkScores,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => $mahasiswa,
                'cpl_summary' => $cplSummary,
                'radar_kategori' => $radarKategori,
                'total_cpl' => count($cplSummary),
                'total_cpl_tercapai' => count(array_filter($cplSummary, fn($c) => $c['skor_rata_rata'] >= 65.0 && $c['total_mata_kuliah_diukur'] > 0)),
                'mk_details' => $mkDetails,
            ]
        ]);
    }

    public function duplicateRps(Request $request, $id)
    {
        $request->validate([
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'nullable|integer|min:1|max:14',
        ]);

        $source = \App\Models\Siakad\Rps::with('mingguan')->findOrFail($id);

        $copy = DB::transaction(function () use ($source, $request) {
            $new = \App\Models\Siakad\Rps::create([
                'mata_kuliah_id' => $source->mata_kuliah_id,
                'tahun_ajaran' => $request->tahun_ajaran,
                'semester' => $request->input('semester', $source->semester),
                'deskripsi_singkat' => $source->deskripsi_singkat,
                'pustaka_utama' => $source->pustaka_utama,
                'pustaka_pendukung' => $source->pustaka_pendukung,
                'dosen_pengembang_id' => $source->dosen_pengembang_id,
                'koordinator_rmk_id' => $source->koordinator_rmk_id,
                'kaprodi_id' => $source->kaprodi_id,
                'status' => 'draft',
                'catatan_revisi' => null,
                'disetujui_at' => null,
            ]);

            foreach ($source->mingguan as $m) {
                \App\Models\Siakad\RpsMingguan::create([
                    'rps_id' => $new->id,
                    'minggu_ke' => $m->minggu_ke,
                    'kemampuan_akhir' => $m->kemampuan_akhir,
                    'bahan_kajian' => $m->bahan_kajian,
                    'bentuk_metode' => $m->bentuk_metode,
                    'estimasi_waktu' => $m->estimasi_waktu,
                    'pengalaman_belajar' => $m->pengalaman_belajar,
                    'indikator_penilaian' => $m->indikator_penilaian,
                    'bobot_penilaian' => $m->bobot_penilaian,
                ]);
            }

            return $new;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'RPS berhasil diimpor dari periode ' . $source->tahun_ajaran . ' sebagai draft. Silakan sesuaikan perubahannya.',
            'data' => $copy->load(['mingguan', 'mataKuliah']),
        ], 201);
    }

    // --- Grafik Capaian CPL & CPMK (bar + drill-down, ala bau evaluatif) ---
    public function getGrafikCpl(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'angkatan' => 'nullable|integer|min:2000|max:2100',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $cpls = Cpl::with('cpmks')
            ->when($request->filled('program_studi_id'), fn($q) => $q->where('program_studi_id', $request->program_studi_id))
            ->where('is_active', true)
            ->orderBy('kode_cpl')
            ->get();

        $result = $cpls->map(function ($cpl) use ($request) {
            $cpmkIds = $cpl->cpmks->pluck('id')->toArray();
            $skor = collect();
            $mhsIds = [];
            if (!empty($cpmkIds)) {
                $rows = KetercapaianCpmkMahasiswa::with('krsDetail.krs')
                    ->whereIn('cpmk_id', $cpmkIds)
                    ->when($request->filled('angkatan'), fn($q) => $q->whereHas('krsDetail.krs.mahasiswa', fn($mq) => $mq->where('angkatan', $request->angkatan)))
                    ->when($request->filled('tahun_akademik_id'), fn($q) => $q->whereHas('krsDetail.krs', fn($kq) => $kq->where('tahun_akademik_id', $request->tahun_akademik_id)))
                    ->get();
                $skor = $rows->pluck('skor_ketercapaian')->map(fn($v) => (float) $v);
                $mhsIds = $rows->map(fn($r) => $r->krsDetail?->krs?->mahasiswa_id)->filter()->unique()->values();
            }

            $avg = $skor->isNotEmpty() ? round($skor->avg(), 1) : 0.0;

            return [
                'cpl_id' => $cpl->id,
                'kode_cpl' => $cpl->kode_cpl,
                'kategori' => $cpl->kategori,
                'deskripsi' => $cpl->deskripsi,
                'skor_rata_rata' => $avg,
                'is_tercapai' => $skor->isNotEmpty() && $avg >= 65.0,
                'total_pengukuran' => $skor->count(),
                'total_mahasiswa' => $mhsIds->count(),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    public function getGrafikCpmk(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'mata_kuliah_id' => 'nullable|exists:siakad_mata_kuliah,id',
            'angkatan' => 'nullable|integer|min:2000|max:2100',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $mkQuery = MataKuliah::with(['cpmks', 'kurikulum.programStudi'])
            ->where('is_active', true)
            ->when($request->filled('mata_kuliah_id'), fn($q) => $q->where('id', $request->mata_kuliah_id))
            ->when($request->filled('program_studi_id'), fn($q) => $q->whereHas('kurikulum', fn($k) => $k->where('program_studi_id', $request->program_studi_id)))
            ->orderBy('kode_mk')
            ->limit(50)
            ->get();

        $result = $mkQuery->map(function ($mk) use ($request) {
            $cpmkRows = $mk->cpmks->map(function ($cpmk) use ($request) {
                $rows = KetercapaianCpmkMahasiswa::with('krsDetail.krs.mahasiswa', 'krsDetail.nilai')
                    ->where('cpmk_id', $cpmk->id)
                    ->when($request->filled('angkatan'), fn($q) => $q->whereHas('krsDetail.krs.mahasiswa', fn($mq) => $mq->where('angkatan', $request->angkatan)))
                    ->when($request->filled('tahun_akademik_id'), fn($q) => $q->whereHas('krsDetail.krs', fn($kq) => $kq->where('tahun_akademik_id', $request->tahun_akademik_id)))
                    ->get();

                $skor = $rows->pluck('skor_ketercapaian')->map(fn($v) => (float) $v);
                $avg = $skor->isNotEmpty() ? round($skor->avg(), 1) : 0.0;

                return [
                    'cpmk_id' => $cpmk->id,
                    'kode_cpmk' => $cpmk->kode_cpmk,
                    'deskripsi' => $cpmk->deskripsi,
                    'bobot_persentase' => (float) $cpmk->bobot_persentase,
                    'skor_rata_rata' => $avg,
                    'is_tercapai' => $skor->isNotEmpty() && $avg >= 65.0,
                    'total_mahasiswa' => $rows->map(fn($r) => $r->krsDetail?->krs?->mahasiswa_id)->filter()->unique()->count(),
                    // Drill-down level mahasiswa
                    'mahasiswa' => $rows->map(fn($r) => [
                        'nim' => $r->krsDetail?->krs?->mahasiswa?->nim,
                        'nama_lengkap' => $r->krsDetail?->krs?->mahasiswa?->nama_lengkap,
                        'skor' => (float) $r->skor_ketercapaian,
                        'is_tercapai' => ((float) $r->skor_ketercapaian) >= 65.0,
                        'nilai_huruf' => $r->krsDetail?->nilai?->nilai_huruf,
                    ])->values(),
                ];
            });

            return [
                'mata_kuliah_id' => $mk->id,
                'kode_mk' => $mk->kode_mk,
                'nama' => $mk->nama,
                'total_sks' => $mk->total_sks,
                'cpmks' => $cpmkRows,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    // --- SubCPMK (di bawah CPMK) ---
    public function getSubCpmk(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $request->validate([
            'cpmk_id' => 'nullable|exists:siakad_cpmk,id',
            'mata_kuliah_id' => 'nullable|exists:siakad_mata_kuliah,id',
        ]);

        $query = SubCpmk::with('cpmk.mataKuliah')->orderBy('kode_sub_cpmk');
        if ($request->filled('cpmk_id')) {
            $query->where('cpmk_id', $request->cpmk_id);
        } elseif ($request->filled('mata_kuliah_id')) {
            $query->whereHas('cpmk', function ($q) use ($request) {
                $q->where('mata_kuliah_id', $request->mata_kuliah_id);
            });
        }

        return response()->json(['status' => 'success', 'data' => $query->get()]);
    }

    public function storeSubCpmk(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $validated = $request->validate([
            'id' => 'nullable|exists:siakad_sub_cpmk,id',
            'cpmk_id' => 'nullable|exists:siakad_cpmk,id',
            'cpmk_prodi_id' => 'nullable|exists:siakad_cpmk_prodi,id',
            'mata_kuliah_id' => 'nullable|exists:siakad_mata_kuliah,id',
            'kode_sub_cpmk' => 'required|string|max:50',
            'deskripsi' => 'required|string',
            'indikator' => 'nullable|string',
            'bobot_persentase' => 'nullable|numeric|min:0|max:100',
        ]);

        $cpmkId = $request->cpmk_id;

        // Jika dikirim cpmk_prodi_id, auto-resolve atau buatkan record siakad_cpmk untuk mata kuliah tersebut
        if (!$cpmkId && $request->filled('cpmk_prodi_id')) {
            $cpmkProdi = CpmkProdi::find($request->cpmk_prodi_id);
            if ($cpmkProdi) {
                $mkId = $request->mata_kuliah_id;
                if (!$mkId) {
                    $firstMk = $cpmkProdi->mataKuliahs()->first();
                    $mkId = $firstMk?->id;
                }

                if ($mkId) {
                    $cpmk = Cpmk::firstOrCreate(
                        [
                            'mata_kuliah_id' => $mkId,
                            'kode_cpmk' => $cpmkProdi->kode_cpmk,
                        ],
                        [
                            'cpl_id' => $cpmkProdi->cpl_id,
                            'deskripsi' => $cpmkProdi->deskripsi,
                            'bobot_persentase' => 0,
                        ]
                    );

                    if ($cpmk->wasRecentlyCreated) {
                        try {
                            AuditLogService::record(
                                module: 'SIAKAD',
                                action: 'create',
                                tableName: 'siakad_cpmk',
                                recordId: $cpmk->id,
                                oldValues: null,
                                newValues: $cpmk->toArray(),
                                request: $request
                            );
                        } catch (\Throwable $e) {
                            Log::warning('Gagal mencatat audit log CPMK auto-create: ' . $e->getMessage());
                        }
                    }

                    $cpmkId = $cpmk->id;
                }
            }
        }

        if (!$cpmkId) {
            return response()->json([
                'status' => 'error',
                'message' => 'CPMK tidak valid atau belum dipilih.'
            ], 422);
        }

        $validated['cpmk_id'] = $cpmkId;
        unset($validated['cpmk_prodi_id'], $validated['mata_kuliah_id']);

        $oldValues = $request->filled('id') ? SubCpmk::find($request->id)?->toArray() : null;
        $sub = SubCpmk::updateOrCreate(['id' => $request->id], $validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $request->filled('id') ? 'update' : 'create',
                tableName: 'siakad_sub_cpmk',
                recordId: $sub->id,
                oldValues: $oldValues,
                newValues: $sub->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log Sub CPMK: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'SubCPMK berhasil disimpan',
            'data' => $sub->load('cpmk.mataKuliah'),
        ], $request->filled('id') ? 200 : 201);
    }

    public function deleteSubCpmk($id)
    {
        Gate::authorize('siakad.nilai.manage');

        $sub = SubCpmk::findOrFail($id);
        if ($sub->komponenPenilaians()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'SubCPMK dipakai komponen penilaian — lepas dulu sebelum dihapus.',
            ], 422);
        }

        $oldValues = $sub->getOriginal();
        $subId = $sub->id;
        $sub->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_sub_cpmk',
                recordId: $subId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus SubCPMK: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'SubCPMK berhasil dihapus.',
            'data' => null,
        ]);
    }

    // --- Bank Soal per Sesi/SubCPMK (master tunggal untuk OBE & Quiz LMS) ---
    public function listSoal(Request $request)
    {
        Gate::authorize('siakad.nilai.manage');

        $perPage = min(100, $request->integer('per_page', 15));

        $query = BankSoal::with(['mingguan', 'subCpmk.cpmk', 'rps.mataKuliah.kurikulum.programStudi', 'kategori', 'opsi']);

        if ($request->filled('rps_id')) {
            $query->where('rps_id', $request->input('rps_id'));
        }
        if ($request->filled('rps_mingguan_id')) {
            $query->where('rps_mingguan_id', $request->input('rps_mingguan_id'));
        }
        if ($request->filled('sub_cpmk_id')) {
            $query->where('sub_cpmk_id', $request->input('sub_cpmk_id'));
        }
        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->input('kategori_id'));
        }
        if ($request->filled('tipe_soal')) {
            $query->where('tipe_soal', $request->input('tipe_soal'));
        }
        if ($request->filled('tingkat_kesulitan')) {
            $query->where('tingkat_kesulitan', $request->input('tingkat_kesulitan'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('pertanyaan', 'like', "%{$search}%")
                  ->orWhere('kunci_jawaban', 'like', "%{$search}%")
                  ->orWhereHas('opsi', function ($o) use ($search) {
                      $o->where('teks', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['id', 'bobot', 'tipe_soal', 'tingkat_kesulitan', 'created_at', 'updated_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'id';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data bank soal berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    public function storeSoal(StoreBankSoalRequest $request)
    {
        Gate::authorize('siakad.nilai.manage');

        $validated = $request->validated();
        $validated['dibuat_oleh'] = $request->user()?->id;

        if (trim(strip_tags($validated['pertanyaan'] ?? '')) === '') {
            return response()->json(['status' => 'error', 'message' => 'Pertanyaan tidak boleh kosong.'], 422);
        }

        if ($request->filled('id')) {
            $soal = BankSoal::findOrFail($request->input('id'));
            $oldValues = $soal->getOriginal();
            $soal->update($validated);
            $newValues = $soal->getChanges();
            $action = 'update';
        } else {
            $soal = BankSoal::create($validated);
            $oldValues = null;
            $newValues = $soal->toArray();
            $action = 'create';
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $action,
                tableName: 'siakad_bank_soal',
                recordId: $soal->id,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log bank soal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Soal berhasil disimpan.',
            'data' => $soal->load(['mingguan', 'subCpmk.cpmk', 'kategori', 'opsi']),
        ], $action === 'create' ? 201 : 200);
    }

    public function deleteSoal($id)
    {
        Gate::authorize('siakad.nilai.manage');

        $soal = BankSoal::findOrFail($id);

        // Bank soal yang sudah dipakai quiz LMS tidak boleh dihapus diam-diam
        // (FK restrict juga menjaganya di level database).
        if (\App\Models\Lms\QuizSoal::where('bank_soal_id', $soal->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Soal dipakai quiz LMS — lepas dari quiz terlebih dahulu sebelum dihapus.',
            ], 422);
        }

        $oldValues = $soal->getOriginal();
        $soalId = $soal->id;
        $soal->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_bank_soal',
                recordId: $soalId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus soal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Soal berhasil dihapus.',
            'data' => null,
        ]);
    }

    // --- Kategori Bank Soal ---
    public function listKategoriSoal(Request $request)
    {
        Gate::authorize('siakad.nilai.manage');

        $perPage = min(100, $request->integer('per_page', 15));
        $query = BankSoalKategori::with('mataKuliah')->withCount('soal');

        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->input('mata_kuliah_id'));
        }
        if ($request->filled('search')) {
            $query->where('nama', 'like', '%' . $request->input('search') . '%');
        }

        $allowedSorts = ['id', 'nama', 'created_at', 'updated_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'nama';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $data = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data kategori bank soal berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    public function storeKategoriSoal(StoreBankSoalKategoriRequest $request)
    {
        Gate::authorize('siakad.nilai.manage');

        $validated = $request->validated();
        $validated['dibuat_oleh'] = $request->user()?->id;

        $kategori = BankSoalKategori::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_bank_soal_kategori',
                recordId: $kategori->id,
                oldValues: null,
                newValues: $kategori->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log kategori bank soal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori bank soal berhasil dibuat.',
            'data' => $kategori->load('mataKuliah'),
        ], 201);
    }

    public function deleteKategoriSoal($id)
    {
        Gate::authorize('siakad.nilai.manage');

        $kategori = BankSoalKategori::findOrFail($id);
        $oldValues = $kategori->getOriginal();
        $recordId = $kategori->id;

        // Soal di dalamnya tidak ikut terhapus — relasi dilepas (kategori_id → null).
        BankSoal::where('kategori_id', $kategori->id)->update(['kategori_id' => null]);
        $kategori->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_bank_soal_kategori',
                recordId: $recordId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus kategori: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori bank soal berhasil dihapus.',
            'data' => null,
        ]);
    }

    // --- Opsi Jawaban Soal Pilihan Ganda ---
    public function storeOpsiSoal(StoreBankSoalOpsiRequest $request, $soalId)
    {
        Gate::authorize('siakad.nilai.manage');

        $soal = BankSoal::findOrFail($soalId);

        $opsi = $soal->opsi()->create($request->validated());

        // Satu soal hanya boleh punya satu opsi benar.
        if ($opsi->is_benar) {
            BankSoalOpsi::where('bank_soal_id', $soal->id)
                ->where('id', '!=', $opsi->id)
                ->update(['is_benar' => false]);
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_bank_soal_opsi',
                recordId: $opsi->id,
                oldValues: null,
                newValues: $opsi->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log opsi bank soal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Opsi jawaban berhasil ditambahkan.',
            'data' => $opsi->fresh(),
        ], 201);
    }

    public function deleteOpsiSoal($soalId, $opsiId)
    {
        Gate::authorize('siakad.nilai.manage');

        $opsi = BankSoalOpsi::where('bank_soal_id', $soalId)->findOrFail($opsiId);
        $oldValues = $opsi->getOriginal();
        $recordId = $opsi->id;

        $opsi->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_bank_soal_opsi',
                recordId: $recordId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus opsi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Opsi jawaban berhasil dihapus.',
            'data' => null,
        ]);
    }

    // --- Rekap Nilai Kelas (XLSX, dibuka di Excel) ---
    public function rekapKelasXlsx($kelasId)
    {
        $kelas = Kelas::with(['mataKuliah', 'tahunAkademik', 'programStudi'])->findOrFail($kelasId);
        $res = $this->getKelasNilaiObe(new Request(), $kelasId);
        $payload = $res->getData(true)['data'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Nilai');

        $sheet->setCellValue('A1', 'REKAP NILAI KELAS');
        $sheet->setCellValue('A2', ($kelas->mataKuliah?->nama ?? '') . ' (' . ($kelas->mataKuliah?->kode_mk ?? '') . ')');
        $sheet->setCellValue('A3', 'Kelas: ' . ($kelas->nama_kelas ?? '') . ' • Periode: ' . ($kelas->tahunAkademik?->nama ?? '') . ' • Prodi: ' . ($kelas->programStudi?->nama ?? ''));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $header = ['NO', 'NIM', 'NAMA'];
        foreach ($payload['komponen'] as $comp) {
            $header[] = ($comp['nama_komponen'] ?? 'Komponen') . ' (' . ($comp['bobot'] ?? 0) . '%)';
        }
        $header = array_merge($header, ['NILAI_AKHIR', 'HURUF', 'MUTU', 'STATUS']);

        $rowNum = 5;
        $colLetter = function ($i) {
            $s = '';
            $i++;
            while ($i > 0) {
                $m = ($i - 1) % 26;
                $s = chr(65 + $m) . $s;
                $i = intdiv($i - $m - 1, 26);
            }
            return $s;
        };
        $lastCol = $colLetter(count($header) - 1);

        foreach (array_values($header) as $i => $h) {
            $sheet->setCellValue($colLetter($i) . $rowNum, $h);
        }
        $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']],
        ]);

        foreach (array_values($payload['peserta']) as $i => $p) {
            $r = $rowNum + 1 + $i;
            $row = [$i + 1, $p['mahasiswa']['nim'] ?? '', $p['mahasiswa']['nama_lengkap'] ?? ''];
            foreach ($payload['komponen'] as $comp) {
                $row[] = $p['scores'][(string) $comp['id']]['nilai_angka'] ?? $p['scores'][$comp['id']]['nilai_angka'] ?? 0;
            }
            $row[] = $p['nilai_akhir'];
            $row[] = $p['nilai_huruf'];
            $row[] = $p['bobot_mutu'];
            $row[] = !empty($p['is_final']) ? 'Final' : 'Draft';
            foreach (array_values($row) as $c => $v) {
                $sheet->setCellValue($colLetter($c) . $r, $v);
            }
        }

        $lastRow = $rowNum + count($payload['peserta']);
        $sheet->getStyle("A{$rowNum}:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'B0B0B0']]],
        ]);
        foreach (range(0, count($header) - 1) as $c) {
            $sheet->getColumnDimension($colLetter($c))->setAutoSize(true);
        }

        $fname = 'rekap_nilai_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas->kode_kelas ?? $kelasId) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, $fname, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function submitRps($id)
    {
        $rps = \App\Models\Siakad\Rps::findOrFail($id);
        $rps->update([
            'status' => 'diajukan',
            'catatan_revisi' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen RPS berhasil diajukan ke Ketua Program Studi (Kaprodi) untuk diverifikasi.',
            'data' => $rps
        ]);
    }

    public function approveRps(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:disetujui,revisi',
            'catatan_revisi' => 'nullable|string',
        ]);

        $rps = \App\Models\Siakad\Rps::with('mataKuliah.kurikulum')->findOrFail($id);
        $prodiId = $rps->mataKuliah?->kurikulum?->program_studi_id;

        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $canApprove = false;
        if ($user->isSuperAdmin() || $user->hasPermission('siakad.master.manage') || $user->hasPermission('siakad.kurikulum.manage')) {
            $canApprove = true;
        } elseif ($prodiId && $user->canApproveRpsForProdi($prodiId)) {
            $canApprove = true;
        }

        if (!$canApprove) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memverifikasi/menyetujui RPS program studi ini.'
            ], 403);
        }

        $dosen = \App\Models\Siakad\Dosen::where('user_id', $user?->id)->first();
        $oldValues = $rps->getOriginal();

        $rps->update([
            'status' => $request->status,
            'catatan_revisi' => $request->catatan_revisi,
            'kaprodi_id' => $dosen ? $dosen->id : $rps->kaprodi_id,
            'disetujui_at' => $request->status === 'disetujui' ? now() : null,
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $request->status === 'disetujui' ? 'approve' : 'reject',
                tableName: 'siakad_rps',
                recordId: $rps->id,
                oldValues: $oldValues,
                newValues: $rps->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log approve RPS: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => $request->status === 'disetujui'
                ? 'RPS berhasil diverifikasi dan disetujui.'
                : 'RPS dikembalikan ke Dosen Pengembang dengan catatan revisi.',
            'data' => $rps
        ]);
    }

    // --- Dashboard Monitoring OBE ---
    public function getObeDashboard(Request $request)
    {
        $prodiId = $request->query('program_studi_id');

        $cplQuery = Cpl::query();
        $cpmkQuery = Cpmk::query();
        $rpsQuery = \App\Models\Siakad\Rps::query();
        $mkQuery = MataKuliah::query();

        if ($prodiId) {
            $cplQuery->where('program_studi_id', $prodiId);
            $mkQuery->whereHas('kurikulum', fn($q) => $q->where('program_studi_id', $prodiId));
            $rpsQuery->whereHas('mataKuliah.kurikulum', fn($q) => $q->where('program_studi_id', $prodiId));
        }

        $totalCpl = $cplQuery->count();
        $totalCpmk = $cpmkQuery->count();
        $totalMk = $mkQuery->count();
        $totalRps = $rpsQuery->count();
        $approvedRps = (clone $rpsQuery)->where('status', 'disetujui')->count();
        $submittedRps = (clone $rpsQuery)->where('status', 'diajukan')->count();
        $draftRps = (clone $rpsQuery)->where('status', 'draft')->count();

        // Rata-rata ketercapaian CPL per kategori (dihitung dari data riil, 0 bila belum dinilai)
        $cpls = $cplQuery->get();
        $cplStats = [
            'sikap' => 0.0,
            'pengetahuan' => 0.0,
            'keterampilan_umum' => 0.0,
            'keterampilan_khusus' => 0.0,
        ];
        if ($cpls->isNotEmpty()) {
            $scores = \App\Models\Siakad\KetercapaianCpmkMahasiswa::with('cpmk.cpl')
                ->when($prodiId, fn($q) => $q->whereHas('cpmk.cpl', fn($cq) => $cq->where('program_studi_id', $prodiId)))
                ->get()
                ->groupBy(fn($r) => $r->cpmk?->cpl?->kategori);
            foreach ($cplStats as $kat => $val) {
                if (isset($scores[$kat]) && $scores[$kat]->isNotEmpty()) {
                    $cplStats[$kat] = round($scores[$kat]->avg(fn($r) => (float) $r->skor_ketercapaian), 1);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_cpl' => $totalCpl,
                    'total_cpmk' => $totalCpmk,
                    'total_matakuliah' => $totalMk,
                    'total_rps' => $totalRps,
                    'rps_disetujui' => $approvedRps,
                    'rps_diajukan' => $submittedRps,
                    'rps_draft' => $draftRps,
                    'persentase_rps_approved' => $totalMk > 0 ? round(($approvedRps / $totalMk) * 100, 1) : 100,
                ],
                'cpl_kategori_stats' => $cplStats,
                'cpl_list' => $cpls,
            ]
        ]);
    }

    // --- Profil Lulusan (PL) ---
    public function getProfilLulusan(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();
        $query = ProfilLulusan::with(['programStudi', 'cpls', 'kurikulum']);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('kode_pl', 'like', "%{$s}%")
                  ->orWhere('nama', 'like', "%{$s}%")
                  ->orWhere('deskripsi', 'like', "%{$s}%");
            });
        }

        $allowedSorts = ['kode_pl', 'nama', 'urutan', 'id', 'created_at'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'kode_pl';
        $sortOrder = strtolower((string) $request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar profil lulusan berhasil diambil',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar profil lulusan berhasil diambil',
            'data' => $query->get()
        ]);
    }

    public function storeProfilLulusan(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');
        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kurikulum_id' => 'nullable|exists:siakad_kurikulum,id',
            'kode_pl' => 'required|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'urutan' => 'nullable|integer',
        ]);

        if (empty($validated['program_studi_id'])) {
            $prodiIds = $user && method_exists($user, 'getSiakadProdiIds') ? $user->getSiakadProdiIds() : collect();
            $validated['program_studi_id'] = $prodiIds->first();
            if (empty($validated['program_studi_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Program studi wajib ditentukan.'
                ], 422);
            }
        }

        $existingPl = ProfilLulusan::where('program_studi_id', $validated['program_studi_id'])
            ->where('kode_pl', $validated['kode_pl'])
            ->first();
        $oldValues = $existingPl ? $existingPl->getOriginal() : null;

        $pl = ProfilLulusan::updateOrCreate(
            [
                'program_studi_id' => $validated['program_studi_id'],
                'kode_pl' => $validated['kode_pl']
            ],
            [
                'kurikulum_id' => $validated['kurikulum_id'] ?? null,
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'],
                'urutan' => $validated['urutan'] ?? 1,
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $pl->wasRecentlyCreated ? 'create' : 'update',
                tableName: 'siakad_profil_lulusan',
                recordId: $pl->id,
                oldValues: $oldValues,
                newValues: $pl->wasRecentlyCreated ? $pl->toArray() : $pl->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil Lulusan berhasil disimpan',
            'data' => $pl->load(['programStudi', 'kurikulum'])
        ], 201);
    }

    public function deleteProfilLulusan($id)
    {
        $pl = ProfilLulusan::findOrFail($id);
        $pl->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil Lulusan berhasil dihapus'
        ]);
    }

    public function mapProfilLulusanCpl(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');
        $request->validate([
            'profil_lulusan_id' => 'required|exists:siakad_profil_lulusan,id',
            'cpl_ids' => 'present|array',
            'cpl_ids.*' => 'exists:siakad_cpl,id',
        ]);

        $pl = ProfilLulusan::findOrFail($request->profil_lulusan_id);
        $oldIds = $pl->cpls()->pluck('siakad_cpl.id')->toArray();
        $pl->cpls()->sync($request->cpl_ids ?? []);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'map_cpl',
                tableName: 'siakad_profil_lulusan_cpl',
                recordId: $pl->id,
                oldValues: ['cpl_ids' => $oldIds],
                newValues: ['cpl_ids' => $request->cpl_ids ?? []],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Profil Lulusan ke CPL berhasil disimpan',
            'data' => $pl->load('cpls')
        ]);
    }

    // --- Bahan Kajian (BK) ---
    public function getBahanKajian(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        $query = BahanKajian::with(['programStudi', 'kurikulum', 'koordinator', 'mataKuliahs', 'cpls']);

        // Data BK hanya milik prodi aktif pengguna. User tanpa prodi aktif
        // tidak boleh melihat data prodi lain sama sekali.
        if ($user && !$user->isSuperAdmin() && method_exists($user, 'getSiakadProdiIds')) {
            $query->whereIn('program_studi_id', $user->getSiakadProdiIds());
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('koordinator_id')) {
            $query->where('koordinator_id', $request->integer('koordinator_id'));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('kode_bk', 'like', "%{$s}%")
                    ->orWhere('nama_bk', 'like', "%{$s}%");
            });
        }

        $allowedSorts = ['kode_bk', 'nama_bk', 'kurikulum_id', 'created_at', 'id'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true)
            ? $request->query('sort_by')
            : 'kode_bk';
        $sortOrder = strtolower((string) $request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar bahan kajian berhasil diambil',
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar bahan kajian berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function storeBahanKajian(StoreBahanKajianRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        if (empty($validated['program_studi_id'])) {
            if (!empty($validated['kurikulum_id'])) {
                $kurikulum = \App\Models\Siakad\Kurikulum::find($validated['kurikulum_id']);
                $validated['program_studi_id'] = $kurikulum?->program_studi_id;
            }
            if (empty($validated['program_studi_id']) && $user && method_exists($user, 'getSiakadProdiIds')) {
                $validated['program_studi_id'] = $user->getSiakadProdiIds()->first();
            }
            if (empty($validated['program_studi_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Program studi wajib ditentukan.'
                ], 422);
            }
        }

        $existing = BahanKajian::where('program_studi_id', $validated['program_studi_id'])
            ->where('kode_bk', $validated['kode_bk'])
            ->first();
        $oldValues = $existing ? $existing->getOriginal() : null;

        $bk = BahanKajian::updateOrCreate(
            [
                'program_studi_id' => $validated['program_studi_id'],
                'kode_bk' => $validated['kode_bk'],
            ],
            [
                'kurikulum_id' => $validated['kurikulum_id'] ?? null,
                'nama_bk' => $validated['nama_bk'],
                'koordinator_id' => $validated['koordinator_id'] ?? null,
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]
        );

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $existing ? 'update' : 'create',
                tableName: 'siakad_bahan_kajian',
                recordId: $bk->id,
                oldValues: $oldValues,
                newValues: $existing ? $bk->getChanges() : $bk->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log bahan kajian: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bahan Kajian berhasil disimpan',
            'data' => $bk->load(['programStudi', 'kurikulum', 'koordinator']),
        ], 201);
    }

    public function updateBahanKajian(StoreBahanKajianRequest $request, int $id)
    {
        $bk = BahanKajian::findOrFail($id);
        $oldValues = $bk->getOriginal();

        $validated = $request->validated();
        $bk->update([
            'program_studi_id' => $validated['program_studi_id'] ?? $bk->program_studi_id,
            'kurikulum_id' => $validated['kurikulum_id'] ?? null,
            'kode_bk' => $validated['kode_bk'] ?? $bk->kode_bk,
            'nama_bk' => $validated['nama_bk'] ?? $bk->nama_bk,
            'koordinator_id' => $validated['koordinator_id'] ?? null,
            'deskripsi' => $validated['deskripsi'] ?? null,
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_bahan_kajian',
                recordId: $bk->id,
                oldValues: $oldValues,
                newValues: $bk->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log bahan kajian: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bahan Kajian berhasil diperbarui',
            'data' => $bk->load(['programStudi', 'kurikulum', 'koordinator']),
        ]);
    }

    public function deleteBahanKajian(Request $request, int $id)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $bk = BahanKajian::findOrFail($id);
        $oldValues = $bk->getOriginal();

        $bk->cpls()->detach();
        $bk->mataKuliahs()->detach();
        $bk->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_bahan_kajian',
                recordId: $id,
                oldValues: $oldValues,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log hapus bahan kajian: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bahan Kajian berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * Matriks pemetaan CPL ↔ BK.
     * Mengembalikan daftar CPL, daftar BK, dan pasangan yang sudah terpetakan.
     */
    public function getMatrixCplBahanKajian(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        $bkQuery = BahanKajian::query()->orderBy('kode_bk');
        $cplQuery = Cpl::query()->where('is_active', true)->orderBy('kode_cpl');

        $scope = function ($query, $user, $request) {
            if ($user && !$user->isSuperAdmin() && method_exists($user, 'getSiakadProdiIds')) {
                $query->whereIn('program_studi_id', $user->getSiakadProdiIds());
            } elseif ($request->filled('program_studi_id')) {
                $query->where('program_studi_id', $request->program_studi_id);
            }
        };

        $scope($bkQuery, $user, $request);
        $scope($cplQuery, $user, $request);

        $bahanKajians = $bkQuery->get(['id', 'kode_bk', 'nama_bk', 'program_studi_id']);
        $cpls = $cplQuery->get(['id', 'kode_cpl', 'kategori', 'deskripsi', 'program_studi_id']);

        $pairs = DB::table('siakad_cpl_bahan_kajian')
            ->join('siakad_bahan_kajian', 'siakad_bahan_kajian.id', '=', 'siakad_cpl_bahan_kajian.bahan_kajian_id')
            ->whereIn('siakad_bahan_kajian.id', $bahanKajians->pluck('id'))
            ->whereIn('siakad_cpl_bahan_kajian.cpl_id', $cpls->pluck('id'))
            ->get(['siakad_cpl_bahan_kajian.cpl_id', 'siakad_cpl_bahan_kajian.bahan_kajian_id']);

        // Matriks hanya relevan bila CPL & BK berasal dari program studi yang sama,
        // sehingga superadmin (yang dapat melihat seluruh prodi) tidak melihat
        // korelasi lintas prodi.
        $prodiCpl = $cpls->pluck('program_studi_id', 'id');
        $prodiBk = $bahanKajians->pluck('program_studi_id', 'id');

        return response()->json([
            'status' => 'success',
            'message' => 'Matriks pemetaan CPL-BK berhasil diambil',
            'data' => [
                'cpls' => $cpls,
                'bahan_kajians' => $bahanKajians,
                'pairs' => $pairs
                    ->filter(fn($p) => (int) $prodiCpl[$p->cpl_id] === (int) $prodiBk[$p->bahan_kajian_id])
                    ->map(fn($p) => [
                        'cpl_id' => (int) $p->cpl_id,
                        'bahan_kajian_id' => (int) $p->bahan_kajian_id,
                    ])->values(),
            ],
        ]);
    }

    /**
     * Simpan pemetaan satu CPL ke daftar Bahan Kajian (checkbox).
     */
    public function syncCplBahanKajian(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');
        $request->validate([
            'cpl_id' => 'required|exists:siakad_cpl,id',
            'bahan_kajian_ids' => 'present|array',
            'bahan_kajian_ids.*' => 'exists:siakad_bahan_kajian,id',
        ]);

        $cpl = Cpl::findOrFail($request->cpl_id);
        $oldIds = $cpl->bahanKajians()->pluck('siakad_bahan_kajian.id')->toArray();
        $newIds = $request->bahan_kajian_ids ?? [];
        $cpl->bahanKajians()->sync($newIds);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'map_bahan_kajian',
                tableName: 'siakad_cpl_bahan_kajian',
                recordId: $cpl->id,
                oldValues: ['bahan_kajian_ids' => $oldIds],
                newValues: ['bahan_kajian_ids' => $newIds],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log pemetaan CPL-BK: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan CPL ke Bahan Kajian berhasil disimpan',
            'data' => $cpl->load('bahanKajians'),
        ]);
    }

    /**
     * Matriks pemetaan BK ↔ MK.
     * Mengembalikan daftar MK, daftar BK, dan pasangan yang sudah terpetakan.
     */
    public function getMatrixBahanKajianMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        $bkQuery = BahanKajian::query()->orderBy('kode_bk');
        $mkQuery = MataKuliah::query()
            ->with('kurikulum')
            ->where('is_active', true)
            ->orderBy('kode_mk');

        if ($user && !$user->isSuperAdmin() && method_exists($user, 'getSiakadProdiIds')) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            $bkQuery->whereIn('program_studi_id', $allowedProdiIds);
            $mkQuery->whereHas('kurikulum', fn($q) => $q->whereIn('program_studi_id', $allowedProdiIds));
        } else {
            if ($request->filled('program_studi_id')) {
                $bkQuery->where('program_studi_id', $request->program_studi_id);
                $mkQuery->whereHas('kurikulum', fn($q) => $q->where('program_studi_id', $request->program_studi_id));
            }
        }

        if ($request->filled('kurikulum_id')) {
            $kurikulumId = $request->integer('kurikulum_id');
            $bkQuery->where('kurikulum_id', $kurikulumId);
            $mkQuery->where('kurikulum_id', $kurikulumId);
        }

        $bahanKajians = $bkQuery->get(['id', 'kode_bk', 'nama_bk', 'program_studi_id']);
        $mataKuliahs = $mkQuery->get(['id', 'kode_mk', 'nama', 'kurikulum_id']);

        $pairs = DB::table('siakad_mata_kuliah_bahan_kajian')
            ->whereIn('bahan_kajian_id', $bahanKajians->pluck('id'))
            ->whereIn('mata_kuliah_id', $mataKuliahs->pluck('id'))
            ->get(['mata_kuliah_id', 'bahan_kajian_id']);

        // Matriks hanya relevan bila MK dan BK berasal dari program studi yang sama.
        $prodiMk = $mataKuliahs->mapWithKeys(fn($m) => [$m->id => (int) ($m->kurikulum?->program_studi_id ?? 0)]);
        $prodiBk = $bahanKajians->pluck('program_studi_id', 'id');

        return response()->json([
            'status' => 'success',
            'message' => 'Matriks pemetaan BK-MK berhasil diambil',
            'data' => [
                'mata_kuliahs' => $mataKuliahs,
                'bahan_kajians' => $bahanKajians,
                'pairs' => $pairs
                    ->filter(fn($p) => (int) $prodiMk[$p->mata_kuliah_id] === (int) $prodiBk[$p->bahan_kajian_id])
                    ->map(fn($p) => [
                        'mata_kuliah_id' => (int) $p->mata_kuliah_id,
                        'bahan_kajian_id' => (int) $p->bahan_kajian_id,
                    ])->values(),
            ],
        ]);
    }

    /**
     * Daftar pasangan (CPL, MK) yang memiliki minimal satu jalur CPL -> BK -> MK.
     *
     * Inilah sumber kebenaran kelayakan sel pada matriks Pemetaan CPL-MK: backend
     * menghitung ulang (bukan rely on cache), sehingga perubahan pada CPL-BK atau
     * BK-MK langsung tercermin tanpa perlu sinkronisasi manual.
     *
     * @param  array<int, int>|int|null  $prodiIds  Satu id prodi atau daftar id prodi.
     * @return \Illuminate\Support\Collection<int, array{cpl_id:int, mata_kuliah_id:int}>
     */
    private function eligibleCplMataKuliahPairs(array|int|null $prodiIds = null): \Illuminate\Support\Collection
    {
        $prodiIds = array_filter(array_map('intval', (array) $prodiIds));
        $jalur = DB::table('siakad_cpl_bahan_kajian as cpl_bk')
            ->join('siakad_mata_kuliah_bahan_kajian as mk_bk', 'mk_bk.bahan_kajian_id', '=', 'cpl_bk.bahan_kajian_id')
            ->join('siakad_cpl as cpl', 'cpl.id', '=', 'cpl_bk.cpl_id')
            ->join('siakad_mata_kuliah as mk', 'mk.id', '=', 'mk_bk.mata_kuliah_id')
            ->join('siakad_kurikulum as kur', 'kur.id', '=', 'mk.kurikulum_id')
            ->where('cpl.is_active', true)
            ->where('mk.is_active', true)
            // CPL dan MK harus berasal dari program studi yang sama, sama seperti
            // guard lintas-prodi pada matriks CPL-BK dan BK-MK.
            ->whereColumn('cpl.program_studi_id', 'kur.program_studi_id')
            ->when($prodiIds !== [], fn($q) => $q->whereIn('cpl.program_studi_id', $prodiIds))
            ->select([
                'cpl_bk.cpl_id',
                'mk_bk.mata_kuliah_id',
            ])
            ->get()
            ->map(fn($r) => [
                'cpl_id' => (int) $r->cpl_id,
                'mata_kuliah_id' => (int) $r->mata_kuliah_id,
            ])
            ->unique(fn($r) => $r['cpl_id'] . '-' . $r['mata_kuliah_id'])
            ->values();

        return $jalur;
    }

    /**
     * Matriks Pemetaan CPL-MK.
     *
     * Mengembalikan tiga lapis informasi dalam satu panggilan:
     *  - `eligible` : pasangan yang memiliki jalur CPL -> BK -> MK (sel boleh dicentang).
     *  - `pairs`    : pasangan yang sudah dicentang user di `siakad_mata_kuliah_cpl`.
     *  - `yatim`    : pasangan yang sudah dicentang tetapi jalur CPL-BK / BK-MK-nya
     *                 sudah berubah. Tetap ditampilkan agar riwayat akreditasi tidak
     *                 hilang, namun ditandai untuk ditinjau kembali.
     */
    public function getMatrixCplMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();
        $prodiId = $this->resolveObeProdiId($request);

        $cpls = Cpl::where('is_active', true)
            ->whereIn('program_studi_id', $prodiId)
            ->orderBy('kode_cpl')
            ->get(['id', 'kode_cpl', 'kategori', 'deskripsi', 'program_studi_id']);

        $mataKuliahs = MataKuliah::with('kurikulum')
            ->where('is_active', true)
            ->whereHas('kurikulum', fn($q) => $q->whereIn('program_studi_id', $prodiId))
            ->orderBy('kode_mk')
            ->get(['id', 'kurikulum_id', 'kode_mk', 'nama', 'total_sks', 'semester_anjuran']);

        $eligible = $this->eligibleCplMataKuliahPairs($prodiId);

        $pairs = DB::table('siakad_mata_kuliah_cpl')
            ->whereIn('cpl_id', $cpls->pluck('id'))
            ->whereIn('mata_kuliah_id', $mataKuliahs->pluck('id'))
            ->get(['cpl_id', 'mata_kuliah_id'])
            ->map(fn($r) => [
                'cpl_id' => (int) $r->cpl_id,
                'mata_kuliah_id' => (int) $r->mata_kuliah_id,
            ])
            ->values();

        $eligibleKeys = $eligible->map(fn($r) => $r['cpl_id'] . '-' . $r['mata_kuliah_id'])->flip();
        $yatimKeys = $pairs
            ->reject(fn($r) => $eligibleKeys->has($r['cpl_id'] . '-' . $r['mata_kuliah_id']))
            ->map(fn($r) => $r['cpl_id'] . '-' . $r['mata_kuliah_id'])
            ->flip();

        return response()->json([
            'status' => 'success',
            'message' => 'Matriks pemetaan CPL-MK berhasil diambil',
            'data' => [
                'cpls' => $cpls,
                'mata_kuliahs' => $mataKuliahs,
                'eligible' => $eligible,
                'pairs' => $pairs,
                'yatim' => $yatimKeys->keys()->values(),
            ],
        ]);
    }

    /**
     * Simpan / lepas satu sel Pemetaan CPL-MK.
     *
     * Guard kelayakan ditegakkan di server: pasangan yang tidak memiliki jalur
     * CPL -> BK -> MK ditolak 422 meskipun request dibuat langsung ke API.
     */
    public function toggleMatrixCplMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');

        $validated = $request->validate([
            'cpl_id' => 'required|exists:siakad_cpl,id',
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'is_checked' => 'required|boolean',
        ]);

        $cplId = (int) $validated['cpl_id'];
        $mataKuliahId = (int) $validated['mata_kuliah_id'];
        $isChecked = $request->boolean('is_checked');

        $cpl = Cpl::find($cplId);
        $mataKuliah = MataKuliah::with('kurikulum')->find($mataKuliahId);

        if (!$cpl || !$mataKuliah) {
            return response()->json(['status' => 'error', 'message' => 'CPL atau mata kuliah tidak ditemukan.'], 404);
        }

        $prodiCpl = (int) $cpl->program_studi_id;
        $prodiMk = (int) ($mataKuliah->kurikulum?->program_studi_id ?? 0);

        if ($prodiCpl !== $prodiMk) {
            abort(422, sprintf(
                'Pemetaan CPL-MK tidak tersedia: %s (%s) dan %s berada pada program studi berbeda.',
                $cpl->kode_cpl,
                $cpl->program_studi_id,
                $mataKuliah->kode_mk
            ));
        }

        if (!in_array($prodiCpl, $this->allowedObeProdiIds($request), true)) {
            abort(403, 'Program studi ini bukan berada pada program studi yang boleh Anda kelola.');
        }

        // Lepas centang selalu boleh terjadi: itu justru perbaikan untuk sel yatim.
        if (!$isChecked) {
            DB::table('siakad_mata_kuliah_cpl')
                ->where('cpl_id', $cplId)
                ->where('mata_kuliah_id', $mataKuliahId)
                ->delete();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'delete',
                    tableName: 'siakad_mata_kuliah_cpl',
                    oldValues: ['cpl_id' => $cplId, 'mata_kuliah_id' => $mataKuliahId],
                    newValues: null,
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal audit log: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pemetaan CPL-MK berhasil dilepas',
                'data' => ['cpl_id' => $cplId, 'mata_kuliah_id' => $mataKuliahId, 'is_checked' => false],
            ]);
        }

        // Sel hanya boleh dicentang bila sudah ada jalur CPL -> BK -> MK.
        $sudahDipetakan = $this->eligibleCplMataKuliahPairs($prodiCpl)
            ->contains(fn($r) => $r['cpl_id'] === $cplId && $r['mata_kuliah_id'] === $mataKuliahId);

        if (!$sudahDipetakan) {
            abort(422, sprintf(
                'Pemetaan CPL-MK tidak tersedia: %s belum memiliki jalur CPL -> BK -> MK menuju %s. Lengkapi Pemetaan CPL-BK dan BK-MK terlebih dahulu.',
                $cpl->kode_cpl,
                $mataKuliah->kode_mk
            ));
        }

        DB::table('siakad_mata_kuliah_cpl')->insertOrIgnore([
            'mata_kuliah_id' => $mataKuliahId,
            'cpl_id' => $cplId,
            'created_at' => now(),
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_mata_kuliah_cpl',
                oldValues: null,
                newValues: ['cpl_id' => $cplId, 'mata_kuliah_id' => $mataKuliahId],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan CPL-MK berhasil disimpan',
            'data' => ['cpl_id' => $cplId, 'mata_kuliah_id' => $mataKuliahId, 'is_checked' => true],
        ]);
    }

    /**
     * Laporan read-only Pemetaan CPL-BK-MK.
     *
     * Baris = Bahan Kajian, kolom = CPL, isi sel = daftar Mata Kuliah yang
     * menjembatani keduanya. murni komposisi CPL -> BK -> MK, tanpa penyimpanan
     * terpisah sehingga tidak mungkin melenceng dari pemetaan induknya.
     */
    public function getMatrixCplBahanKajianMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.read');

        $prodiId = $this->resolveObeProdiId($request);

        $cpls = Cpl::where('is_active', true)
            ->whereIn('program_studi_id', $prodiId)
            ->orderBy('kode_cpl')
            ->get(['id', 'kode_cpl', 'kategori', 'deskripsi', 'program_studi_id']);

        // `siakad_bahan_kajian` tidak memiliki kolom is_active; bahan kajian aktif
        // ditentukan oleh kurikulum aktif yang dirujuknya.
        $bahanKajians = BahanKajian::whereIn('program_studi_id', $prodiId)
            ->whereHas('kurikulum', fn($q) => $q->where('is_active', true))
            ->orderBy('kode_bk')
            ->get(['id', 'kode_bk', 'nama_bk', 'program_studi_id']);

        $jembatan = DB::table('siakad_cpl_bahan_kajian as cpl_bk')
            ->join('siakad_mata_kuliah_bahan_kajian as mk_bk', 'mk_bk.bahan_kajian_id', '=', 'cpl_bk.bahan_kajian_id')
            ->join('siakad_mata_kuliah as mk', 'mk.id', '=', 'mk_bk.mata_kuliah_id')
            ->join('siakad_kurikulum as kur', 'kur.id', '=', 'mk.kurikulum_id')
            ->whereIn('cpl_bk.cpl_id', $cpls->pluck('id'))
            ->whereIn('cpl_bk.bahan_kajian_id', $bahanKajians->pluck('id'))
            ->where('mk.is_active', true)
            // Guard lintas-prodi: CPL, BK, dan MK harus satu program studi.
            ->join('siakad_bahan_kajian as bk', 'bk.id', '=', 'cpl_bk.bahan_kajian_id')
            ->join('siakad_cpl as cpl', 'cpl.id', '=', 'cpl_bk.cpl_id')
            ->whereColumn('bk.program_studi_id', 'kur.program_studi_id')
            ->whereColumn('cpl.program_studi_id', 'kur.program_studi_id')
            ->select([
                'cpl_bk.cpl_id',
                'cpl_bk.bahan_kajian_id',
                'mk.id as mata_kuliah_id',
                'mk.kode_mk',
                'mk.nama as nama_mk',
            ])
            ->get();

        // Grouping per (CPL, BK) dengan MK unik, mengikuti urutan appearance.
        $isi = [];
        foreach ($jembatan as $r) {
            $isi[$r->cpl_id][$r->bahan_kajian_id] ??= [];
            $isi[$r->cpl_id][$r->bahan_kajian_id][$r->mata_kuliah_id] = [
                'kode_mk' => $r->kode_mk,
                'nama_mk' => $r->nama_mk,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Matriks pemetaan CPL-BK-MK berhasil diambil',
            'data' => [
                'cpls' => $cpls,
                'bahan_kajians' => $bahanKajians,
                'isi' => collect($isi)->map(fn($perCpl) => collect($perCpl)->map(fn($perMk) => array_values($perMk))->all())->all(),
            ],
        ]);
    }

    /**
     * Simpan pemetaan satu Bahan Kajian ke daftar Mata Kuliah (checkbox).
     */
    public function syncBahanKajianMataKuliah(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');
        $request->validate([
            'bahan_kajian_id' => 'required|exists:siakad_bahan_kajian,id',
            'mata_kuliah_ids' => 'present|array',
            'mata_kuliah_ids.*' => 'exists:siakad_mata_kuliah,id',
        ]);

        $bk = BahanKajian::findOrFail($request->bahan_kajian_id);
        $oldIds = $bk->mataKuliahs()->pluck('siakad_mata_kuliah.id')->toArray();
        $newIds = $request->mata_kuliah_ids ?? [];
        $bk->mataKuliahs()->sync($newIds);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'map_mata_kuliah',
                tableName: 'siakad_mata_kuliah_bahan_kajian',
                recordId: $bk->id,
                oldValues: ['mata_kuliah_ids' => $oldIds],
                newValues: ['mata_kuliah_ids' => $newIds],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log pemetaan BK-MK: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Bahan Kajian ke Mata Kuliah berhasil disimpan',
            'data' => $bk->load('mataKuliahs'),
        ]);
    }

    public function mapMataKuliahBahanKajian(Request $request)
    {
        Gate::authorize('siakad.kurikulum.manage');
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'bahan_kajian_ids' => 'present|array',
            'bahan_kajian_ids.*' => 'exists:siakad_bahan_kajian,id',
        ]);

        $mk = MataKuliah::findOrFail($request->mata_kuliah_id);
        $oldIds = $mk->bahanKajians()->pluck('siakad_bahan_kajian.id')->toArray();
        $mk->bahanKajians()->sync($request->bahan_kajian_ids ?? []);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'map_bahan_kajian',
                tableName: 'siakad_mata_kuliah_bahan_kajian',
                recordId: $mk->id,
                oldValues: ['bahan_kajian_ids' => $oldIds],
                newValues: ['bahan_kajian_ids' => $request->bahan_kajian_ids ?? []],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Mata Kuliah ke Bahan Kajian berhasil disimpan',
            'data' => $mk->load('bahanKajians')
        ]);
    }

    // --- Pemantauan & Audit Kelengkapan Pemetaan OBE per Mata Kuliah ---
    public function getAuditPemetaan(Request $request)
    {
        $prodiId = $request->input('program_studi_id');
        $taId = $request->input('tahun_akademik_id') ?? \App\Models\Spmb\MasterTahunAkademik::where('is_active', true)->value('id');

        $matakuliahs = MataKuliah::with(['cpls', 'cpmks', 'kurikulum.programStudi', 'kelas' => function($k) use ($taId) {
                if ($taId) $k->where('tahun_akademik_id', $taId);
                $k->with(['dosenPengampu.dosen', 'krsDetails']);
            }])
            ->where('is_active', true)
            ->when($prodiId, function($q) use ($prodiId) {
                $q->whereHas('kurikulum', fn($k) => $k->where('program_studi_id', $prodiId));
            })
            ->orderBy('semester_anjuran')
            ->orderBy('kode_mk')
            ->get();

        $auditList = $matakuliahs->map(function ($mk) {
            $totalBobotCpmk = (float) $mk->cpmks->sum('bobot_persentase');
            $cpmkCount = $mk->cpmks->count();
            $cplCount = $mk->cpls->count();

            // Status Bobot
            $isBobot100 = abs($totalBobotCpmk - 100.0) < 0.01;
            $statusBobot = $cpmkCount === 0
                ? 'belum_ada_cpmk'
                : ($isBobot100 ? 'lengkap_100' : ($totalBobotCpmk < 100 ? 'kurang_100' : 'lebih_100'));

            // Kelas & Dosen Pengampu Aktif
            $totalKelas = $mk->kelas->count();
            $totalMahasiswa = $mk->kelas->sum(fn($k) => $k->krsDetails->where('status', 'aktif')->count());
            $dosenPengampus = $mk->kelas->flatMap(function($k) {
                return $k->dosenPengampu->map(fn($dp) => [
                    'id' => $dp->dosen?->id,
                    'nama_lengkap' => $dp->dosen?->nama_lengkap,
                    'peran' => $dp->peran,
                    'kelas' => $k->nama_kelas,
                ]);
            })->filter(fn($d) => !empty($d['nama_lengkap']))->unique('id')->values();

            // Status Kelayakan Penilaian Dosen
            $siapDinilai = $isBobot100 && $cpmkCount > 0;

            return [
                'id' => $mk->id,
                'kode_mk' => $mk->kode_mk,
                'nama' => $mk->nama,
                'total_sks' => $mk->total_sks,
                'semester_default' => $mk->semester_anjuran,
                'semester_anjuran' => $mk->semester_anjuran,
                'program_studi' => [
                    'id' => $mk->kurikulum?->programStudi?->id,
                    'nama' => $mk->kurikulum?->programStudi?->nama,
                ],
                'cpl_count' => $cplCount,
                'cpmk_count' => $cpmkCount,
                'total_bobot_cpmk' => $totalBobotCpmk,
                'status_bobot' => $statusBobot,
                'siap_dinilai' => $siapDinilai,
                'total_kelas' => $totalKelas,
                'total_mahasiswa_krs' => $totalMahasiswa,
                'dosen_pengampu' => $dosenPengampus,
            ];
        });

        // Ringkasan Dashboard Audit
        $totalMk = $auditList->count();
        $mkSiapDinilai = $auditList->where('siap_dinilai', true)->count();
        $mkBelum100 = $auditList->where('siap_dinilai', false)->count();
        $mkTanpaCpmk = $auditList->where('cpmk_count', 0)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Data audit pemetaan OBE berhasil dimuat',
            'data' => [
                'summary' => [
                    'total_matakuliah' => $totalMk,
                    'siap_dinilai' => $mkSiapDinilai,
                    'belum_lengkap' => $mkBelum100,
                    'tanpa_cpmk' => $mkTanpaCpmk,
                    'persentase_kesiapan' => $totalMk > 0 ? round(($mkSiapDinilai / $totalMk) * 100, 1) : 0,
                ],
                'audit_items' => $auditList,
            ]
        ]);
    }

    // --- Pemantauan Ketertiban Dosen Menginput Nilai (Integrasi SIMPEG Kinerja) ---
    public function getDosenKepatuhanNilai(Request $request)
    {
        $taId = $request->input('tahun_akademik_id') ?? \App\Models\Spmb\MasterTahunAkademik::where('is_active', true)->value('id');
        $ta = \App\Models\Spmb\MasterTahunAkademik::find($taId);

        $now = now();
        $batasNilaiMulai = $ta?->input_nilai_mulai;
        $batasNilaiSelesai = $ta?->input_nilai_selesai;

        // Ambil seluruh dosen yang mengampu kelas pada tahun akademik ini
        $kelasQuery = Kelas::with(['dosenPengampu.dosen.pegawai', 'krsDetails.nilai', 'mataKuliah'])
            ->where('tahun_akademik_id', $taId);

        $kelases = $kelasQuery->get();

        $dosenStats = [];

        foreach ($kelases as $k) {
            foreach ($k->dosenPengampu as $dp) {
                if (!$dp->dosen) continue;
                $dId = $dp->dosen->id;

                if (!isset($dosenStats[$dId])) {
                    $dosenStats[$dId] = [
                        'dosen_id' => $dId,
                        'nama_lengkap' => $dp->dosen->nama_lengkap,
                        'nidn' => $dp->dosen->nidn,
                        'nip' => $dp->dosen->nip,
                        'pegawai_id' => $dp->dosen->pegawai?->id ?? null,
                        'total_kelas' => 0,
                        'total_mahasiswa' => 0,
                        'mahasiswa_dinilai' => 0,
                        'mahasiswa_final' => 0,
                        'kelas_selesai' => 0,
                        'status_kepatuhan' => 'tepat_waktu', // tepat_waktu, dalam_proses, terlambat
                    ];
                }

                $dosenStats[$dId]['total_kelas']++;
                $krsAktif = $k->krsDetails->where('status', 'aktif');
                $dosenStats[$dId]['total_mahasiswa'] += $krsAktif->count();

                $graded = $krsAktif->filter(fn($kd) => $kd->nilai && $kd->nilai->nilai_angka > 0 || ($kd->nilai && $kd->nilai->nilai_huruf));
                $finalized = $krsAktif->filter(fn($kd) => $kd->nilai && $kd->nilai->is_final);

                $dosenStats[$dId]['mahasiswa_dinilai'] += $graded->count();
                $dosenStats[$dId]['mahasiswa_final'] += $finalized->count();

                if ($krsAktif->count() > 0 && $finalized->count() >= $krsAktif->count()) {
                    $dosenStats[$dId]['kelas_selesai']++;
                }
            }
        }

        // Hitung persentase ketertiban & skor kinerja
        $result = collect($dosenStats)->values()->map(function ($d) use ($batasNilaiSelesai, $now) {
            $totalMhs = $d['total_mahasiswa'];
            $pctFinal = $totalMhs > 0 ? round(($d['mahasiswa_final'] / $totalMhs) * 100, 1) : 100.0;
            $pctInput = $totalMhs > 0 ? round(($d['mahasiswa_dinilai'] / $totalMhs) * 100, 1) : 100.0;

            // Evaluasi kepatuhan deadline
            $isDeadlinePassed = $batasNilaiSelesai && $now->greaterThan(\Carbon\Carbon::parse($batasNilaiSelesai));
            
            if ($pctFinal >= 100.0) {
                $statusKepatuhan = 'lengkap_final';
                $skorKepatuhan = 100.0;
            } elseif ($isDeadlinePassed) {
                $statusKepatuhan = 'terlambat';
                $skorKepatuhan = max(30.0, round($pctFinal * 0.7, 1));
            } else {
                $statusKepatuhan = 'sedang_berjalan';
                $skorKepatuhan = max(50.0, round($pctFinal, 1));
            }

            return array_merge($d, [
                'persentase_input' => $pctInput,
                'persentase_final' => $pctFinal,
                'status_kepatuhan' => $statusKepatuhan,
                'skor_kinerja_akademik' => $skorKepatuhan,
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data ketertiban & kepatuhan pengisian nilai dosen berhasil dimuat',
            'data' => [
                'tahun_akademik' => $ta,
                'periode_nilai' => [
                    'mulai' => $batasNilaiMulai,
                    'selesai' => $batasNilaiSelesai,
                    'is_expired' => $batasNilaiSelesai ? $now->greaterThan(\Carbon\Carbon::parse($batasNilaiSelesai)) : false,
                ],
                'summary' => [
                    'total_dosen_mengajar' => $result->count(),
                    'dosen_selesai_100' => $result->where('status_kepatuhan', 'lengkap_final')->count(),
                    'dosen_terlambat' => $result->where('status_kepatuhan', 'terlambat')->count(),
                    'dosen_sedang_berjalan' => $result->where('status_kepatuhan', 'sedang_berjalan')->count(),
                ],
                'dosen_kepatuhan' => $result,
            ]
        ]);
    }

    /**
     * Daftar id program studi yang boleh diakses user pada matriks OBE.
     *
     * Mengembalikan array berisi id agar bisa dipakai langsung pada `in_array`
     * strict comparison dan `whereIn`.
     *
     * @return array<int, int>
     */
    private function allowedObeProdiIds(Request $request): array
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'getSiakadProdiIds')) {
            return [];
        }

        return $user->getSiakadProdiIds()
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Program studi aktif yang dipakai matriks OBE.
     *
     * Sesuai aturan OBE admin, halaman tidak menyediakan input program studi karena
     * prodi mengikuti prodi aktif akun. Superadmin tetap dapat membatasi tampilan
     * lewat query string `program_studi_id` tanpa mengabaikan scope prodi aktif.
     *
     * @return array<int, int>
     */
    private function resolveObeProdiId(Request $request): array
    {
        $allowed = $this->allowedObeProdiIds($request);
        $requested = $request->filled('program_studi_id') ? (int) $request->program_studi_id : null;

        if ($requested && in_array($requested, $allowed, true)) {
            return [$requested];
        }

        return $allowed;
    }
}
