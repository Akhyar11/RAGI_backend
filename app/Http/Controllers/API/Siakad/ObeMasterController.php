<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Models\Siakad\RumpunMataKuliah;
use App\Models\Siakad\JenisCpl;
use App\Models\Siakad\ObeRubrik;
use App\Models\Siakad\ObeRubrikKriteria;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MasterKelas;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ObeMasterController extends Controller
{
    // ==========================================
    // 1. RUMPUN MATA KULIAH
    // ==========================================
    public function listRumpunMk(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();
        $query = RumpunMataKuliah::with(['programStudi', 'dosenKoordinator']);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            // Scoped user: hanya data prodi sendiri (baris global disembunyikan).
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where(function($q) use ($request) {
                $q->where('program_studi_id', $request->program_studi_id)
                  ->orWhereNull('program_studi_id');
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('nama_rumpun', 'like', "%{$s}%")
                  ->orWhere('kode_rumpun', 'like', "%{$s}%");
            });
        }

        if ($request->filled('dosen_koordinator_id')) {
            $query->where('dosen_koordinator_id', $request->integer('dosen_koordinator_id'));
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['kode_rumpun', 'nama_rumpun', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama_rumpun';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar rumpun mata kuliah berhasil diambil',
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
            'message' => 'Daftar rumpun mata kuliah berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function storeRumpunMk(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode_rumpun' => 'nullable|string|max:50',
            'nama_rumpun' => 'required|string|max:150',
            'dosen_koordinator_id' => 'nullable|exists:siakad_dosen,id',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['kode_rumpun'])) {
            $validated['kode_rumpun'] = 'RMP-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $validated['nama_rumpun']), 0, 4)) . '-' . rand(10, 99);
        }

        // Scoped user tanpa pilihan prodi: atribusikan ke prodi sendiri agar baris
        // tidak menjadi global (global disembunyikan dari user prodi saat list).
        if (empty($validated['program_studi_id'])) {
            $scoped = $user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()
                ? $user->getSiakadProdiIds()
                : collect();
            if ($scoped->isNotEmpty()) {
                $validated['program_studi_id'] = $scoped->first();
            }
        }

        $item = RumpunMataKuliah::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_rumpun_mk',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumpun Mata Kuliah berhasil ditambahkan',
            'data' => $item->load(['programStudi', 'dosenKoordinator']),
        ], 201);
    }

    public function updateRumpunMk(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = RumpunMataKuliah::findOrFail($id);
        $old = $item->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode_rumpun' => 'nullable|string|max:50',
            'nama_rumpun' => 'required|string|max:150',
            'dosen_koordinator_id' => 'nullable|exists:siakad_dosen,id',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $new = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_rumpun_mk',
                recordId: $item->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumpun Mata Kuliah berhasil diperbarui',
            'data' => $item->load(['programStudi', 'dosenKoordinator']),
        ]);
    }

    public function destroyRumpunMk(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = RumpunMataKuliah::findOrFail($id);
        $old = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_rumpun_mk',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rumpun Mata Kuliah berhasil dihapus',
            'data' => null,
        ]);
    }

    // ==========================================
    // 2. JENIS CPL
    // ==========================================
    public function listJenisCpl(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();
        $query = JenisCpl::with('programStudi');

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            // Scoped user: hanya data prodi sendiri (baris global disembunyikan).
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where(function($q) use ($request) {
                $q->where('program_studi_id', $request->program_studi_id)
                  ->orWhereNull('program_studi_id');
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('nama_jenis', 'like', "%{$s}%")
                  ->orWhere('kode_jenis', 'like', "%{$s}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['kode_jenis', 'nama_jenis', 'urutan', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'urutan';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar jenis CPL berhasil diambil',
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
            'message' => 'Daftar jenis CPL berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function storeJenisCpl(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode_jenis' => 'required|string|max:50|unique:siakad_jenis_cpl,kode_jenis',
            'nama_jenis' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        // Scoped user tanpa pilihan prodi: atribusikan ke prodi sendiri agar baris
        // tidak menjadi global (global disembunyikan dari user prodi saat list).
        if (empty($validated['program_studi_id'])) {
            $scoped = $user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()
                ? $user->getSiakadProdiIds()
                : collect();
            if ($scoped->isNotEmpty()) {
                $validated['program_studi_id'] = $scoped->first();
            }
        }

        $item = JenisCpl::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_jenis_cpl',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis CPL berhasil ditambahkan',
            'data' => $item->load('programStudi'),
        ], 201);
    }

    public function updateJenisCpl(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = JenisCpl::findOrFail($id);
        $old = $item->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode_jenis' => 'required|string|max:50|unique:siakad_jenis_cpl,kode_jenis,' . $id,
            'nama_jenis' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'urutan' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $new = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_jenis_cpl',
                recordId: $item->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis CPL berhasil diperbarui',
            'data' => $item->load('programStudi'),
        ]);
    }

    public function destroyJenisCpl(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = JenisCpl::findOrFail($id);
        $old = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_jenis_cpl',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis CPL berhasil dihapus',
            'data' => null,
        ]);
    }

    // ==========================================
    // 3. PROFESI / PROSPEK KARIR
    // ==========================================
    public function listProfesiKarir(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();
        $query = \App\Models\Siakad\ProfesiKarir::with('programStudi');

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            // Scoped user: hanya data prodi sendiri (baris global disembunyikan).
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('sumber', 'like', "%{$s}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSort = ['nama', 'sumber', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar profesi karir berhasil diambil',
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
            'message' => 'Daftar profesi karir berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function storeProfesiKarir(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nama' => 'required|string|max:255',
            'sumber' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['program_studi_id'])) {
            $prodiIds = $user && method_exists($user, 'getSiakadProdiIds') ? $user->getSiakadProdiIds() : collect();
            $validated['program_studi_id'] = $prodiIds->first();
            if (empty($validated['program_studi_id'])) {
                return response()->json(['status' => 'error', 'message' => 'Program studi wajib ditentukan.'], 422);
            }
        }

        $item = \App\Models\Siakad\ProfesiKarir::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_profesi_karir',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profesi / Prospek Karir berhasil ditambahkan',
            'data' => $item->load('programStudi'),
        ], 201);
    }

    public function updateProfesiKarir(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\ProfesiKarir::findOrFail($id);
        $old = $item->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nama' => 'required|string|max:255',
            'sumber' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $new = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_profesi_karir',
                recordId: $item->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profesi / Prospek Karir berhasil diperbarui',
            'data' => $item->load('programStudi'),
        ]);
    }

    public function destroyProfesiKarir(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\ProfesiKarir::findOrFail($id);
        $old = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_profesi_karir',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profesi / Prospek Karir berhasil dihapus',
            'data' => null,
        ]);
    }

    // ==========================================
    // 4. RUBRIK PENILAIAN OBE
    // ==========================================
    public function listRubrik(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();
        $query = ObeRubrik::with(['programStudi', 'cpmk', 'kriterias']);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('cpmk_id')) {
            $query->where('cpmk_id', $request->cpmk_id);
        }

        if ($request->filled('tipe_rubrik')) {
            $query->where('tipe_rubrik', $request->tipe_rubrik);
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('nama_rubrik', 'like', "%{$s}%")
                  ->orWhere('kode_rubrik', 'like', "%{$s}%");
            });
        }

        $allowedSort = ['kode_rubrik', 'nama_rubrik', 'tipe_rubrik', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama_rubrik';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar rubrik penilaian berhasil diambil',
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
            'message' => 'Daftar rubrik penilaian berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function showRubrik(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $rubrik = ObeRubrik::with(['programStudi', 'cpmk', 'kriterias'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'message' => 'Detail rubrik berhasil diambil',
            'data' => $rubrik,
        ]);
    }

    public function storeRubrik(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'cpmk_id' => 'nullable|exists:siakad_cpmk,id',
            'kode_rubrik' => 'required|string|max:50|unique:siakad_obe_rubrik,kode_rubrik',
            'nama_rubrik' => 'required|string|max:255',
            'tipe_rubrik' => 'required|in:holistik,analitik,skala_persepsi',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'kriterias' => 'nullable|array',
            'kriterias.*.nama_kriteria' => 'required|string|max:255',
            'kriterias.*.bobot_persen' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.skor_min' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.skor_max' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.deskripsi' => 'nullable|string',
            'kriterias.*.deskripsi_sangat_baik' => 'nullable|string',
            'kriterias.*.deskripsi_baik' => 'nullable|string',
            'kriterias.*.deskripsi_cukup' => 'nullable|string',
            'kriterias.*.deskripsi_kurang' => 'nullable|string',
        ]);

        $validated['program_studi_id'] = $this->resolveRubrikProdiId($request, $validated['program_studi_id'] ?? null);

        $rubrik = ObeRubrik::create([
            'program_studi_id' => $validated['program_studi_id'],
            'cpmk_id' => $validated['cpmk_id'] ?? null,
            'kode_rubrik' => $validated['kode_rubrik'],
            'nama_rubrik' => $validated['nama_rubrik'],
            'tipe_rubrik' => $validated['tipe_rubrik'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['kriterias'])) {
            $urutan = 1;
            foreach ($validated['kriterias'] as $k) {
                ObeRubrikKriteria::create([
                    'rubrik_id' => $rubrik->id,
                    'nama_kriteria' => $k['nama_kriteria'],
                    'bobot_persen' => $k['bobot_persen'] ?? 0,
                    'skor_min' => $k['skor_min'] ?? 0,
                    'skor_max' => $k['skor_max'] ?? 100,
                    'deskripsi' => $k['deskripsi'] ?? null,
                    'deskripsi_sangat_baik' => $k['deskripsi_sangat_baik'] ?? null,
                    'deskripsi_baik' => $k['deskripsi_baik'] ?? null,
                    'deskripsi_cukup' => $k['deskripsi_cukup'] ?? null,
                    'deskripsi_kurang' => $k['deskripsi_kurang'] ?? null,
                    'urutan' => $urutan++,
                ]);
            }
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_obe_rubrik',
                recordId: $rubrik->id,
                oldValues: null,
                newValues: $rubrik->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik Penilaian OBE berhasil dibuat',
            'data' => $rubrik->load(['programStudi', 'cpmk', 'kriterias']),
        ], 201);
    }

    public function updateRubrik(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $rubrik = ObeRubrik::findOrFail($id);
        $old = $rubrik->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'cpmk_id' => 'nullable|exists:siakad_cpmk,id',
            'kode_rubrik' => 'required|string|max:50|unique:siakad_obe_rubrik,kode_rubrik,' . $id,
            'nama_rubrik' => 'required|string|max:255',
            'tipe_rubrik' => 'required|in:holistik,analitik,skala_persepsi',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'kriterias' => 'nullable|array',
            'kriterias.*.nama_kriteria' => 'required|string|max:255',
            'kriterias.*.bobot_persen' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.skor_min' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.skor_max' => 'nullable|numeric|min:0|max:100',
            'kriterias.*.deskripsi' => 'nullable|string',
            'kriterias.*.deskripsi_sangat_baik' => 'nullable|string',
            'kriterias.*.deskripsi_baik' => 'nullable|string',
            'kriterias.*.deskripsi_cukup' => 'nullable|string',
            'kriterias.*.deskripsi_kurang' => 'nullable|string',
        ]);

        $rubrik->update([
            // Prodi rubrik bersifat tetap: tidak berasal dari input pengguna dan tidak
            // dipindahkan saat edit, mengikuti aturan OBE admin (prodi always aktif).
            'program_studi_id' => $rubrik->program_studi_id,
            'cpmk_id' => $validated['cpmk_id'] ?? null,
            'kode_rubrik' => $validated['kode_rubrik'],
            'nama_rubrik' => $validated['nama_rubrik'],
            'tipe_rubrik' => $validated['tipe_rubrik'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $new = $rubrik->getChanges();

        if (isset($validated['kriterias'])) {
            ObeRubrikKriteria::where('rubrik_id', $rubrik->id)->delete();
            $urutan = 1;
            foreach ($validated['kriterias'] as $k) {
                ObeRubrikKriteria::create([
                    'rubrik_id' => $rubrik->id,
                    'nama_kriteria' => $k['nama_kriteria'],
                    'bobot_persen' => $k['bobot_persen'] ?? 0,
                    'skor_min' => $k['skor_min'] ?? 0,
                    'skor_max' => $k['skor_max'] ?? 100,
                    'deskripsi' => $k['deskripsi'] ?? null,
                    'deskripsi_sangat_baik' => $k['deskripsi_sangat_baik'] ?? null,
                    'deskripsi_baik' => $k['deskripsi_baik'] ?? null,
                    'deskripsi_cukup' => $k['deskripsi_cukup'] ?? null,
                    'deskripsi_kurang' => $k['deskripsi_kurang'] ?? null,
                    'urutan' => $urutan++,
                ]);
            }
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_obe_rubrik',
                recordId: $rubrik->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik Penilaian OBE berhasil diperbarui',
            'data' => $rubrik->load(['programStudi', 'cpmk', 'kriterias']),
        ]);
    }

    public function destroyRubrik(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $rubrik = ObeRubrik::findOrFail($id);
        $old = $rubrik->getOriginal();
        $rubrik->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_obe_rubrik',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rubrik Penilaian OBE berhasil dihapus',
            'data' => null,
        ]);
    }

    // ==========================================
    // 5. DISTRIBUSI MENGAJAR / DISTRIBUSI MATA KULIAH
    // ==========================================
    public function listDistribusiMengajar(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');

        $user = $request->user();

        $query = \App\Models\Siakad\DistribusiMengajar::with([
            'programStudi',
            'tahunAkademik',
            'kurikulum',
            'mataKuliah',
            'dosenKoordinator',
        ]);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            $query->whereIn('program_studi_id', $allowedProdiIds);
        } elseif ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->mata_kuliah_id);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('mataKuliah', fn($mq) => $mq->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"))
                  ->orWhereHas('dosenKoordinator', fn($dq) => $dq->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nik', 'like', "%{$s}%")->orWhere('nidn', 'like', "%{$s}%"));
            });
        }

        $allowedSort = ['semester', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'semester';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar distribusi mengajar berhasil diambil',
                'data' => $this->attachDistribusiRelations($data->items()),
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
            'message' => 'Daftar distribusi mengajar berhasil diambil',
            'data' => $this->attachDistribusiRelations($query->get()->all()),
        ]);
    }

    /**
     * Lengkapi setiap baris distribusi dengan daftar dosen anggota
     * (nama, bukan sekadar ID) dan daftar kode kelas perkuliahan
     * yang dibuka untuk MK + tahun akademik tersebut.
     */
    private function attachDistribusiRelations(array $items): array
    {
        $anggotaIds = collect($items)
            ->pluck('dosen_anggota_ids')
            ->flatten()
            ->map(fn($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        $anggotaMap = $anggotaIds->isNotEmpty()
            ? \App\Models\Siakad\Dosen::whereIn('id', $anggotaIds)->get()->keyBy('id')
            : collect();

        $mkIds = collect($items)->pluck('mata_kuliah_id')->filter()->unique()->values();
        $masterKelasIds = collect($items)->pluck('kelas_ids')->flatten()->map(fn($v) => (int) $v)->filter()->unique()->values();

        $masterKelasMap = $masterKelasIds->isNotEmpty()
            ? \App\Models\Siakad\MasterKelas::whereIn('id', $masterKelasIds)->get()->keyBy('id')
            : collect();

        $kelasRows = $mkIds->isNotEmpty()
            ? \App\Models\Siakad\Kelas::whereIn('mata_kuliah_id', $mkIds)
                ->get(['id', 'mata_kuliah_id', 'tahun_akademik_id', 'kode_kelas'])
            : collect();

        return array_map(function ($item) use ($anggotaMap, $kelasRows, $masterKelasMap) {
            $row = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            $ids = $item->dosen_anggota_ids ?? $row['dosen_anggota_ids'] ?? [];
            $ids = collect(is_array($ids) ? $ids : [])->map(fn($v) => (int) $v)->filter()->values();
            $row['dosen_anggotas'] = $ids->map(fn($id) => $anggotaMap->get($id))->filter()->values()->all();

            $kIds = $item->kelas_ids ?? $row['kelas_ids'] ?? [];
            $kIds = collect(is_array($kIds) ? $kIds : [])->map(fn($v) => (int) $v)->filter()->values();
            $masterKelasNames = $kIds->map(fn($kid) => $masterKelasMap->get($kid)?->nama_kelas)->filter()->values()->all();

            $autoKelasList = $kelasRows
                ->where('mata_kuliah_id', (int) ($item->mata_kuliah_id ?? $row['mata_kuliah_id'] ?? 0))
                ->when(isset($item->tahun_akademik_id) || isset($row['tahun_akademik_id']), function ($c) use ($item, $row) {
                    $taId = $item->tahun_akademik_id ?? $row['tahun_akademik_id'] ?? null;
                    return $taId ? $c->where('tahun_akademik_id', (int) $taId) : $c;
                })
                ->pluck('kode_kelas')
                ->filter()
                ->values()
                ->all();

            $row['kelas_list'] = !empty($masterKelasNames) ? $masterKelasNames : $autoKelasList;
            $row['master_kelas_items'] = $kIds->map(fn($kid) => $masterKelasMap->get($kid))->filter()->values()->all();

            return $row;
        }, $items);
    }

    public function storeDistribusiMengajar(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'kurikulum_id' => 'nullable|exists:siakad_kurikulum,id',
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'semester' => 'required|integer|min:1|max:8',
            'dosen_koordinator_id' => 'nullable|exists:siakad_dosen,id',
            'dosen_anggota_ids' => 'nullable|array',
            'kelas_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['program_studi_id'])) {
            $mk = MataKuliah::with('kurikulum')->find($validated['mata_kuliah_id']);
            $validated['program_studi_id'] = $mk?->kurikulum?->program_studi_id;
            if (empty($validated['program_studi_id'])) {
                $prodiIds = $user && method_exists($user, 'getSiakadProdiIds') ? $user->getSiakadProdiIds() : collect();
                $validated['program_studi_id'] = $prodiIds->first();
            }
            if (empty($validated['program_studi_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Program studi wajib ditentukan.'
                ], 422);
            }
        }

        $item = \App\Models\Siakad\DistribusiMengajar::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_distribusi_mengajar',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Distribusi mengajar berhasil ditambahkan',
            'data' => $item->load(['programStudi', 'tahunAkademik', 'kurikulum', 'mataKuliah', 'dosenKoordinator']),
        ], 201);
    }

    public function updateDistribusiMengajar(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\DistribusiMengajar::findOrFail($id);
        $old = $item->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'kurikulum_id' => 'nullable|exists:siakad_kurikulum,id',
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'semester' => 'required|integer|min:1|max:8',
            'dosen_koordinator_id' => 'nullable|exists:siakad_dosen,id',
            'dosen_anggota_ids' => 'nullable|array',
            'kelas_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $new = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_distribusi_mengajar',
                recordId: $item->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Distribusi mengajar berhasil diperbarui',
            'data' => $item->load(['programStudi', 'tahunAkademik', 'kurikulum', 'mataKuliah', 'dosenKoordinator']),
        ]);
    }

    public function destroyDistribusiMengajar(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\DistribusiMengajar::findOrFail($id);
        $old = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_distribusi_mengajar',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Distribusi mengajar berhasil dihapus',
            'data' => null,
        ]);
    }

    public function getDistribusiMataKuliah(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        $query = MataKuliah::with(['kurikulum.programStudi', 'rumpunMataKuliah'])
            ->where('is_active', true);

        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            $query->whereHas('kurikulum', function($q) use ($allowedProdiIds) {
                $q->whereIn('program_studi_id', $allowedProdiIds);
            });
        } elseif ($request->filled('program_studi_id')) {
            $query->whereHas('kurikulum', function($q) use ($request) {
                $q->where('program_studi_id', $request->program_studi_id);
            });
        }

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->kurikulum_id);
        }

        $allMk = $query->orderBy('semester_anjuran', 'asc')
            ->orderBy('kode_mk', 'asc')
            ->get();

        // Mengelompokkan berdasarkan semester 1 s.d. 8
        $distribusi = [];
        for ($sem = 1; $sem <= 8; $sem++) {
            $mkSemester = $allMk->where('semester_anjuran', $sem)->values();
            $distribusi[] = [
                'semester' => $sem,
                'total_sks' => $mkSemester->sum('total_sks'),
                'total_mk' => $mkSemester->count(),
                'mata_kuliahs' => $mkSemester,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Distribusi mata kuliah berhasil diambil',
            'data' => [
                'total_sks_keseluruhan' => $allMk->sum('total_sks'),
                'total_mk_keseluruhan' => $allMk->count(),
                'semesters' => $distribusi,
            ],
        ]);
    }

    /**
     * ==========================================
     * 6. MASTER KELAS OBE & PEMETAAN MAHASISWA
     * ==========================================
     */
    public function listMasterKelas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        $query = \App\Models\Siakad\MasterKelas::with([
            'programStudi',
            'dosenPa',
        ])->withCount(['mahasiswas']);

        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        } elseif ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            if ($allowedProdiIds->isNotEmpty()) {
                $query->whereIn('program_studi_id', $allowedProdiIds);
            }
        }

        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }

        if ($request->filled('dosen_pa_id')) {
            $query->where('dosen_pa_id', $request->dosen_pa_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama_kelas', 'like', "%{$s}%")
                  ->orWhere('keterangan', 'like', "%{$s}%")
                  ->orWhereHas('dosenPa', fn($dq) => $dq->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nidn', 'like', "%{$s}%"));
            });
        }

        $allowedSort = ['nama_kelas', 'tahun_angkatan', 'id', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'nama_kelas';
        $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $perPage = min(100, $request->integer('per_page', $request->integer('limit', 15)));
            $data = $query->paginate($perPage);
            return response()->json([
                'status' => 'success',
                'message' => 'Daftar master kelas berhasil diambil',
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
            'message' => 'Daftar master kelas berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function storeMasterKelas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $user = $request->user();
        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nama_kelas' => 'required|string|max:50',
            'tahun_angkatan' => 'nullable|integer|min:2000|max:2100',
            'dosen_pa_id' => 'nullable|exists:siakad_dosen,id',
            'keterangan' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['program_studi_id'])) {
            $validated['program_studi_id'] = $this->resolveRubrikProdiId($request, null);
        }

        $item = \App\Models\Siakad\MasterKelas::create($validated);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_master_kelas',
                recordId: $item->id,
                oldValues: null,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master kelas berhasil ditambahkan',
            'data' => $item->load(['programStudi', 'dosenPa']),
        ], 201);
    }

    public function updateMasterKelas(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\MasterKelas::findOrFail($id);
        $old = $item->getOriginal();

        $validated = $request->validate([
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'nama_kelas' => 'required|string|max:50',
            'tahun_angkatan' => 'nullable|integer|min:2000|max:2100',
            'dosen_pa_id' => 'nullable|exists:siakad_dosen,id',
            'keterangan' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);
        $new = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_master_kelas',
                recordId: $item->id,
                oldValues: $old,
                newValues: $new,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master kelas berhasil diperbarui',
            'data' => $item->load(['programStudi', 'dosenPa']),
        ]);
    }

    public function destroyMasterKelas(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $item = \App\Models\Siakad\MasterKelas::findOrFail($id);
        $old = $item->toArray();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_master_kelas',
                recordId: $id,
                oldValues: $old,
                newValues: null,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Master kelas berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * Mengambil daftar mahasiswa untuk pemetaan kelas (dikunci per prodi aktif).
     */
    public function listMahasiswaForPemetaan(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.read');
        $user = $request->user();

        // Scope prodi aktif dari user OBE
        $scopedProdiIds = $user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()
            ? $user->getSiakadProdiIds()
            : collect();

        $prodiId = $request->filled('program_studi_id')
            ? (int) $request->program_studi_id
            : ($scopedProdiIds->first() ?: null);

        $query = Mahasiswa::with(['programStudi', 'dosenWali'])
            ->where('status', 'aktif');

        if ($scopedProdiIds->isNotEmpty()) {
            $query->whereIn('program_studi_id', $scopedProdiIds);
        } elseif ($prodiId) {
            $query->where('program_studi_id', $prodiId);
        }

        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->angkatan);
        }

        if ($request->filled('kelas')) {
            $query->where('kelas', $request->kelas);
        }

        if ($request->boolean('hanya_belum_ada_kelas')) {
            $query->where(function($q) {
                $q->whereNull('kelas')->orWhere('kelas', '');
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama_lengkap', 'like', "%{$s}%")
                  ->orWhere('nim', 'like', "%{$s}%")
                  ->orWhere('kelas', 'like', "%{$s}%");
            });
        }

        $perPage = min(100, $request->integer('per_page', $request->integer('limit', 25)));
        $data = $query->orderBy('nim', 'asc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar mahasiswa untuk pemetaan kelas berhasil diambil',
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

    /**
     * Simpan pemetaan mahasiswa ke kelas & sinkronkan dosen PA jika diset.
     */
    public function assignMahasiswaToKelas(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kurikulum.manage');

        $validated = $request->validate([
            'master_kelas_id' => 'required|exists:siakad_master_kelas,id',
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'required|exists:siakad_mahasiswa,id',
            'sync_dosen_pa' => 'boolean',
        ]);

        $user = $request->user();
        $masterKelas = \App\Models\Siakad\MasterKelas::with('dosenPa')->findOrFail($validated['master_kelas_id']);

        // Pastikan master kelas ini sesuai dengan prodi aktif user (jika scoped)
        if ($user && method_exists($user, 'getSiakadProdiIds') && !$user->isSuperAdmin()) {
            $allowedProdiIds = $user->getSiakadProdiIds();
            if (!$allowedProdiIds->contains($masterKelas->program_studi_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses untuk memetakan kelas pada program studi ini.',
                ], 403);
            }
        }

        $queryMhs = Mahasiswa::whereIn('id', $validated['mahasiswa_ids'])
            ->where('program_studi_id', $masterKelas->program_studi_id);

        $updateData = [
            'kelas' => $masterKelas->nama_kelas,
        ];

        // Jika sync_dosen_pa aktif dan master kelas punya dosen_pa_id, update dosen_wali_id juga
        if ($request->boolean('sync_dosen_pa', true) && $masterKelas->dosen_pa_id) {
            $updateData['dosen_wali_id'] = $masterKelas->dosen_pa_id;
        }

        $count = $queryMhs->update($updateData);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_mahasiswa',
                recordId: null,
                oldValues: null,
                newValues: [
                    'master_kelas_id' => $masterKelas->id,
                    'nama_kelas' => $masterKelas->nama_kelas,
                    'mahasiswa_ids' => $validated['mahasiswa_ids'],
                    'updated_count' => $count,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil memetakan {$count} mahasiswa ke kelas {$masterKelas->nama_kelas}.",
            'data' => [
                'updated_count' => $count,
                'kelas' => $masterKelas->nama_kelas,
            ]
        ]);
    }

    /**
     * Resolver program studi untuk penyimpanan rubrik.
     *
     * Rubrik tidak memiliki `kurikulum_id` sehingga prodi tidak dapat diturunkan dari
     * data lain di payload. Sesuai aturan OBE admin, halaman tidak menyediakan input
     * program studi karena prodi selalu aktif milik pengguna. Backend menerjemahkan
     * aturan tersebut menjadi data:
     *  - 1 prodi aktif  -> dipakai otomatis.
     *  - 0 prodi aktif  -> 422 (user belum punya prodi aktif).
     *  - >1 prodi aktif -> 422, kecuali request menyertakan `program_studi_id` yang
     *                      memang berada di dalam rentang prodi aktif user.
     */
    private function resolveRubrikProdiId(Request $request, ?int $submitted): int
    {
        $user = $request->user();
        $scoped = $user && method_exists($user, 'getSiakadProdiIds')
            ? $user->getSiakadProdiIds()
            : collect();

        if ($scoped->count() === 1) {
            return (int) $scoped->first();
        }

        if ($scoped->isEmpty()) {
            abort(422, 'Program studi tidak dapat ditentukan: akun Anda belum memiliki program studi aktif.');
        }

        if ($submitted && $scoped->contains($submitted)) {
            return (int) $submitted;
        }

        abort(422, 'Program studi tidak dapat ditentukan: akun Anda terhubung ke lebih dari satu program studi aktif. Kirimkan parameter program_studi_id secara eksplisit.');
    }
}
