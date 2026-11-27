<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\StoreMasterSekolahRequest;
use App\Models\Spmb\MasterSekolah;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterSekolahController extends Controller
{
    /** Baca: admin SPMB atau pengguna yang boleh melihat/mengisi pendaftaran. */
    private function authorizeRead(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasPermission('spmb.manage') || $user->hasPermission('spmb.pendaftaran.read')),
            403,
            'Akses ditolak.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $perPage = min(100, $request->integer('per_page', 15));
        $sortOrder = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';

        $query = MasterSekolah::query()->orderBy('nama', $sortOrder);

        if ($request->filled('search')) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $request->query('search')));
            $query->where('nama', 'like', "%{$search}%");
        }

        $paginator = $query->paginate($perPage, ['id', 'nama', 'alamat', 'jenis']);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar sekolah berhasil diambil',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(StoreMasterSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $nama = trim(preg_replace('/\s+/u', ' ', $validated['nama']));

        $sekolah = MasterSekolah::firstOrCreate(
            ['nama_normal' => MasterSekolah::normalize($nama)],
            [
                'nama' => $nama,
                'alamat' => $validated['alamat'] ?? null,
                'jenis' => $validated['jenis'] ?? null,
                'sumber' => MasterSekolah::SUMBER_MANUAL,
                'created_by' => $request->user()?->id,
            ],
        );
        $created = $sekolah->wasRecentlyCreated;

        if ($created) {
            $this->audit($request, 'master_sekolah_dibuat', $sekolah, null, $sekolah->only(['nama', 'alamat', 'jenis']));
        } elseif (empty($sekolah->alamat) && ! empty($validated['alamat'])) {
            $old = $sekolah->only(['alamat']);
            $sekolah->update(['alamat' => $validated['alamat']]);
            $this->audit($request, 'master_sekolah_alamat_diperbarui', $sekolah, $old, $sekolah->only(['alamat']));
        }

        return response()->json([
            'status' => 'success',
            'message' => $created ? 'Sekolah berhasil ditambahkan' : 'Sekolah sudah terdaftar',
            'data' => [
                'id' => $sekolah->id,
                'nama' => $sekolah->nama,
                'alamat' => $sekolah->alamat,
                'jenis' => $sekolah->jenis,
                'created' => $created,
            ],
        ], $created ? 201 : 200);
    }

    private function audit(Request $request, string $action, MasterSekolah $sekolah, ?array $old, ?array $new): void
    {
        try {
            AuditLogService::record(
                module: 'SPMB',
                action: $action,
                tableName: $sekolah->getTable(),
                recordId: $sekolah->id,
                oldValues: $old,
                newValues: $new,
                request: $request,
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log master sekolah: '.$exception->getMessage());
        }
    }
}
