<?php

namespace App\Http\Controllers\Arsip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Arsip\KlasifikasiSuratRequest;
use App\Models\Arsip\KlasifikasiSurat;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KlasifikasiSuratController extends Controller
{
    /**
     * Menampilkan daftar master klasifikasi & kode unit surat.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.master.read') && !$user->hasPermission('arsip.master.manage') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat master klasifikasi surat.',
            ], 403);
        }

        $query = KlasifikasiSurat::query();

        if (!empty($request->kategori)) {
            $query->where('kategori', $request->kategori);
        }

        if (isset($request->is_active) && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Jika minta semua untuk dropdown/select
        if ($request->boolean('all')) {
            $items = $query->orderBy('kategori')->orderBy('kode')->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Seluruh data klasifikasi surat berhasil dimuat.',
                'data' => $items,
            ]);
        }

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->orderBy('kategori')->orderBy('kode')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar klasifikasi surat berhasil dimuat.',
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
                'kategori' => $request->kategori,
                'is_active' => $request->is_active,
            ],
        ]);
    }

    /**
     * Menambahkan master klasifikasi / kode unit baru.
     */
    public function store(KlasifikasiSuratRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.master.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengelola klasifikasi surat.',
            ], 403);
        }

        $validated = $request->validated();
        $validated['kode'] = strtoupper(trim($validated['kode']));

        $item = KlasifikasiSurat::create($validated);

        try {
            AuditLogService::record(
                module: 'ARSIP',
                action: 'create_klasifikasi_surat',
                tableName: 'core_arsip_klasifikasi',
                recordId: $item->id,
                oldValues: [],
                newValues: $item->toArray()
            );
        } catch (\Throwable $e) {
            \Log::warning('Gagal mencatat audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Klasifikasi surat berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * Menampilkan rincian klasifikasi surat.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('arsip.master.read') && !$user->hasPermission('arsip.master.manage') && !$user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat rincian klasifikasi surat.',
            ], 403);
        }

        $item = KlasifikasiSurat::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Rincian klasifikasi surat berhasil dimuat.',
            'data' => $item,
        ]);
    }

    /**
     * Memperbarui klasifikasi surat.
     */
    public function update(KlasifikasiSuratRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.master.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengelola klasifikasi surat.',
            ], 403);
        }

        $item = KlasifikasiSurat::findOrFail($id);
        $oldValues = $item->getOriginal();

        $validated = $request->validated();
        if (isset($validated['kode'])) {
            $validated['kode'] = strtoupper(trim($validated['kode']));
        }
        $item->update($validated);
        $newValues = $item->getChanges();

        try {
            AuditLogService::record(
                module: 'ARSIP',
                action: 'update_klasifikasi_surat',
                tableName: 'core_arsip_klasifikasi',
                recordId: $item->id,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            \Log::warning('Gagal mencatat audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Klasifikasi surat berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    /**
     * Menghapus klasifikasi surat.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('arsip.master.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menghapus klasifikasi surat.',
            ], 403);
        }

        $item = KlasifikasiSurat::findOrFail($id);
        $oldValues = $item->getOriginal();
        $item->delete();

        try {
            AuditLogService::record(
                module: 'ARSIP',
                action: 'delete_klasifikasi_surat',
                tableName: 'core_arsip_klasifikasi',
                recordId: $id,
                oldValues: $oldValues,
                newValues: []
            );
        } catch (\Throwable $e) {
            \Log::warning('Gagal mencatat audit log: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Klasifikasi surat berhasil dihapus.',
            'data' => null,
        ]);
    }
}
