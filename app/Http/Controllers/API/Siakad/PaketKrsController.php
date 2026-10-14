<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\StorePaketMkRombelRequest;
use App\Http\Requests\Siakad\UpdatePaketMkRombelRequest;
use App\Models\Siakad\HakaksesKrs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaketMkRombel;
use App\Models\Siakad\TahunAkademik;
use App\Services\Siakad\KrsService;
use App\Services\Siakad\PaketKrsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaketKrsController extends Controller
{
    public function __construct(
        protected PaketKrsService $paketService,
        protected KrsService $krsService
    ) {}

    // --- PAKET MK PER ROMBEL ---
    public function index(Request $request)
    {
        Gate::authorize('siakad.krs.read');

        $query = PaketMkRombel::with(['masterKelas.programStudi', 'mataKuliah', 'tahunAkademik']);
        if ($request->filled('master_kelas_id')) {
            $query->where('master_kelas_id', $request->master_kelas_id);
        }
        if ($request->filled('tahun_akademik_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('tahun_akademik_id', $request->tahun_akademik_id)->orWhereNull('tahun_akademik_id');
            });
        }
        if ($request->filled('is_wajib')) {
            $query->where('is_wajib', $request->boolean('is_wajib'));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mataKuliah', fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"));
        }

        $allowedSort = ['semester_anjuran', 'created_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'semester_anjuran';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket mata kuliah rombel berhasil dimuat',
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

    public function store(StorePaketMkRombelRequest $request)
    {
        Gate::authorize('siakad.kelas.manage');

        $kunci = [
            'master_kelas_id' => $request->master_kelas_id,
            'mata_kuliah_id' => $request->mata_kuliah_id,
            'tahun_akademik_id' => $request->tahun_akademik_id ?? null,
        ];

        $existing = PaketMkRombel::where($kunci)->first();
        if ($existing) {
            return response()->json([
                'status' => 'success',
                'message' => 'Mata kuliah sudah ada di paket rombel',
                'data' => $existing->load(['masterKelas', 'mataKuliah']),
            ], 200);
        }

        $item = PaketMkRombel::create(array_merge($kunci, [
            'semester_anjuran' => $request->semester_anjuran,
            'is_wajib' => $request->boolean('is_wajib', true),
        ]));

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $item->id,
                newValues: $item->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tambah paket: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil ditambahkan ke paket rombel',
            'data' => $item->load(['masterKelas', 'mataKuliah']),
        ], 201);
    }

    public function show($id)
    {
        Gate::authorize('siakad.krs.read');

        $item = PaketMkRombel::with(['masterKelas.programStudi', 'mataKuliah', 'tahunAkademik'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail paket mata kuliah berhasil dimuat',
            'data' => $item,
        ]);
    }

    public function update(UpdatePaketMkRombelRequest $request, $id)
    {
        Gate::authorize('siakad.kelas.manage');

        $item = PaketMkRombel::findOrFail($id);
        $oldValues = $item->getOriginal();
        $item->update($request->validated());

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $item->id,
                oldValues: $oldValues,
                newValues: $item->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log update paket: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Paket mata kuliah berhasil diperbarui',
            'data' => $item->load(['masterKelas', 'mataKuliah']),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        Gate::authorize('siakad.kelas.manage');

        $item = PaketMkRombel::findOrFail($id);
        $oldValues = $item->toArray();
        $item->delete();

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'delete',
                tableName: 'siakad_paket_mk_rombel',
                recordId: $item->id,
                oldValues: $oldValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus paket: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mata kuliah berhasil dihapus dari paket rombel',
            'data' => null,
        ]);
    }

    /**
     * Sinkronisasi massal: ganti seluruh isi paket satu rombel (+periode opsional)
     * dengan daftar MK dari UI centang.
     */
    public function bulkSync(Request $request)
    {
        Gate::authorize('siakad.kelas.manage');

        $validated = $request->validate([
            'master_kelas_id' => 'required|exists:siakad_master_kelas,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
            'items' => 'required|array|min:1',
            'items.*.mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'items.*.semester_anjuran' => 'nullable|integer|min:1|max:14',
            'items.*.is_wajib' => 'nullable|boolean',
        ]);

        $taId = $validated['tahun_akademik_id'] ?? null;
        return DB::transaction(function () use ($validated, $taId, $request) {
            $keepIds = [];
            foreach ($validated['items'] as $row) {
                $item = PaketMkRombel::updateOrCreate(
                    [
                        'master_kelas_id' => $validated['master_kelas_id'],
                        'mata_kuliah_id' => $row['mata_kuliah_id'],
                        'tahun_akademik_id' => $taId,
                    ],
                    [
                        'semester_anjuran' => $row['semester_anjuran'] ?? null,
                        'is_wajib' => array_key_exists('is_wajib', $row) ? (bool) $row['is_wajib'] : true,
                    ]
                );
                $keepIds[] = $item->id;
            }
            // Hapus satu per satu agar observer audit (deleted) terpicu
            $toDelete = PaketMkRombel::where('master_kelas_id', $validated['master_kelas_id'])
                ->where('tahun_akademik_id', $taId)
                ->whereNotIn('id', $keepIds)
                ->get();
            foreach ($toDelete as $delItem) {
                $delItem->delete();
            }
            $deleted = $toDelete->count();

            $data = PaketMkRombel::with('mataKuliah')
                ->where('master_kelas_id', $validated['master_kelas_id'])
                ->where('tahun_akademik_id', $taId)
                ->orderBy('semester_anjuran')
                ->get();

            try {
                \App\Services\AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_paket_mk_rombel',
                    recordId: null,
                    oldValues: ['dihapus' => $deleted],
                    newValues: ['master_kelas_id' => $validated['master_kelas_id'], 'total_item' => $data->count()],
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log bulkSync paket: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Paket rombel disinkronkan ({$data->count()} MK, {$deleted} dihapus)",
                'data' => $data,
            ]);
        });
    }

    // --- PAKET SAYA + AMBIL 1-KLIK ---
    public function paketSaya(Request $request)
    {
        Gate::authorize('siakad.krs.read');

        $user = $request->user();
        $mhs = $this->krsService->resolveMahasiswa($user?->id, $request->input('mahasiswa_id'));
        if ($guard = $this->krsService->assertBolehLihatMahasiswa($user, (int) $mhs->id)) {
            return $guard;
        }
        $ta = $request->filled('tahun_akademik_id')
            ? TahunAkademik::findOrFail($request->tahun_akademik_id)
            : TahunAkademik::where('is_active', true)->firstOrFail();

        $rombel = $this->paketService->resolveRombel($mhs);
        $items = $rombel ? PaketMkRombel::with('mataKuliah')
            ->where('master_kelas_id', $rombel->id)
            ->where(fn($q) => $q->where('tahun_akademik_id', $ta->id)->orWhereNull('tahun_akademik_id'))
            ->orderBy('semester_anjuran')
            ->get() : collect();

        $akses = HakaksesKrs::where('mahasiswa_id', $mhs->id)->where('tahun_akademik_id', $ta->id)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket mata kuliah rombel berhasil dimuat',
            'data' => [
                'rombel' => $rombel?->load('programStudi'),
                'is_krs_dibuka' => !$akses || (bool) $akses->is_dibuka,
                'items' => $items,
            ],
        ]);
    }

    public function ambilPaket(Request $request)
    {
        Gate::authorize('siakad.krs.create');

        $request->validate([
            'mahasiswa_id' => 'nullable|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $user = $request->user();
        $mhs = $this->krsService->resolveMahasiswa($user?->id, $request->input('mahasiswa_id'));
        if ($guard = $this->krsService->assertBolehLihatMahasiswa($user, (int) $mhs->id)) {
            return $guard;
        }
        $ta = $request->filled('tahun_akademik_id')
            ? TahunAkademik::findOrFail($request->tahun_akademik_id)
            : TahunAkademik::where('is_active', true)->firstOrFail();

        [$rombel, $hasil] = $this->paketService->ambilPaket($mhs, (int) $ta->id);

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_krs',
                recordId: (int) $mhs->id,
                newValues: [
                    'mahasiswa_id' => $mhs->id,
                    'tahun_akademik_id' => $ta->id,
                    'hasil' => $hasil,
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ambilPaket: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengambilan paket selesai: ' . count($hasil['berhasil']) . ' berhasil, ' . count($hasil['gagal']) . ' gagal, ' . count($hasil['dilewati']) . ' dilewati',
            'data' => [
                'rombel' => $rombel->load('programStudi'),
                'hasil' => $hasil,
            ],
        ]);
    }

    // --- HAK AKSES KRS ---
    public function listHakakses(Request $request)
    {
        Gate::authorize('siakad.krs.approve');

        $query = HakaksesKrs::with(['mahasiswa.programStudi', 'tahunAkademik']);
        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->tahun_akademik_id);
        }
        if ($request->filled('is_dibuka')) {
            $query->where('is_dibuka', $request->boolean('is_dibuka'));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mahasiswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));
        }

        $allowedSort = ['created_at', 'updated_at', 'id'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, $request->integer('per_page', 15));
        $data = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data hak akses KRS berhasil dimuat',
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

    public function setHakakses(Request $request)
    {
        Gate::authorize('siakad.krs.approve');

        $validated = $request->validate([
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'is_dibuka' => 'required|boolean',
        ]);

        $existing = HakaksesKrs::where('mahasiswa_id', $validated['mahasiswa_id'])
            ->where('tahun_akademik_id', $validated['tahun_akademik_id'])
            ->first();
        $oldValues = $existing ? $existing->toArray() : [];

        $akses = HakaksesKrs::updateOrCreate(
            ['mahasiswa_id' => $validated['mahasiswa_id'], 'tahun_akademik_id' => $validated['tahun_akademik_id']],
            ['is_dibuka' => $validated['is_dibuka'], 'dibuka_at' => $validated['is_dibuka'] ? now() : null]
        );

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: $akses->wasRecentlyCreated ? 'create' : 'update',
                tableName: 'siakad_hakakses_krs',
                recordId: $akses->id,
                oldValues: $oldValues,
                newValues: $akses->getChanges(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log setHakakses: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => $akses->is_dibuka ? 'Akses KRS dibuka.' : 'Akses KRS dikunci.',
            'data' => $akses->load('mahasiswa'),
        ]);
    }

    public function setHakaksesRombel(Request $request)
    {
        Gate::authorize('siakad.krs.approve');

        $validated = $request->validate([
            'master_kelas_id' => 'required|exists:siakad_master_kelas,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'is_dibuka' => 'required|boolean',
        ]);

        $hasil = $this->paketService->setAksesRombel(
            (int) $validated['master_kelas_id'],
            (int) $validated['tahun_akademik_id'],
            (bool) $validated['is_dibuka']
        );

        try {
            \App\Services\AuditLogService::record(
                module: 'SIAKAD',
                action: 'update',
                tableName: 'siakad_hakakses_krs',
                recordId: null,
                newValues: array_merge($validated, ['hasil' => $hasil]),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hakakses rombel: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => $validated['is_dibuka']
                ? "Akses KRS dibuka untuk {$hasil['total']} mahasiswa rombel."
                : "Akses KRS dikunci untuk {$hasil['total']} mahasiswa rombel.",
            'data' => $hasil,
        ]);
    }
}
