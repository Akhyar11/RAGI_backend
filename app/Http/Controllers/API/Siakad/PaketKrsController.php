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
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MasterKelas;
use App\Models\Siakad\MataKuliah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaketKrsController extends Controller
{
    public function __construct(
        protected PaketKrsService $paketService,
        protected KrsService $krsService
    ) {}

    /**
     * Mengambil 2 list mata kuliah untuk rombel terpilih:
     * 1. Mata kuliah yang SUDAH PERNAH diambil / dipaketkan oleh kelas tersebut.
     * 2. Mata kuliah yang BELUM PERNAH diambil oleh kelas tersebut dari kurikulum prodi.
     */
    public function getMataKuliahByRombelHistory(Request $request)
    {
        Gate::authorize('siakad.krs.read');

        $request->validate([
            'master_kelas_id' => 'required|exists:siakad_master_kelas,id',
            'tahun_akademik_id' => 'nullable|exists:siakad_tahun_akademik,id',
        ]);

        $rombel = MasterKelas::with('programStudi')->findOrFail($request->master_kelas_id);
        $taId = $request->filled('tahun_akademik_id') ? (int) $request->tahun_akademik_id : null;

        // 1. Ambil data paket yang tersimpan pada rombel ini
        $paketRecords = PaketMkRombel::with(['tahunAkademik', 'mataKuliah.kurikulum', 'nomorSurat'])
            ->where('master_kelas_id', $rombel->id)
            ->get();
        $paketMkIds = $paketRecords->pluck('mata_kuliah_id')->unique()->toArray();

        // Cari nomor SK aktif untuk rombel pada periode/tahun akademik ini
        $currentPaketItems = $paketRecords->filter(fn($p) => $taId ? ($p->tahun_akademik_id === $taId || is_null($p->tahun_akademik_id)) : true);
        $activeNomorSk = $currentPaketItems->pluck('nomor_sk')->filter()->first();
        $skInfo = null;
        if ($activeNomorSk) {
            $surat = \App\Models\Arsip\NomorSurat::where('nomor_surat', $activeNomorSk)->first();
            if ($surat) {
                $tglSurat = $surat->tanggal_surat ? (is_string($surat->tanggal_surat) ? $surat->tanggal_surat : (string) $surat->tanggal_surat) : null;
                $dokumenPath = $surat->file_path ?: ($surat->file_surat_path ?: $surat->request?->dokumen_lampiran_path);
                $dokumenUrl = $dokumenPath ? app(\App\Services\Storage\FileStorageService::class)->url($dokumenPath) : null;
                $skInfo = [
                    'id' => $surat->id,
                    'nomor_surat' => $surat->nomor_surat,
                    'status' => $surat->status,
                    'is_valid' => $surat->status !== 'dibatalkan' && (bool) $surat->has_dokumen,
                    'has_document' => (bool) $surat->has_dokumen,
                    'perihal' => $surat->perihal,
                    'tujuan' => $surat->tujuan,
                    'kode_unit' => $surat->kode_unit,
                    'tanggal_surat' => $tglSurat,
                    'dokumen_url' => $dokumenUrl,
                ];
            }
        }

        // 2. Ambil MK yang sudah pernah diambil mahasiswa rombel ini di KRS
        $mhsIds = Mahasiswa::where('program_studi_id', $rombel->program_studi_id)
            ->where('kelas', $rombel->nama_kelas)
            ->pluck('id');

        $krsMkIds = KrsDetail::whereHas('krs', fn($q) => $q->whereIn('mahasiswa_id', $mhsIds))
            ->whereHas('kelas', fn($q) => $q->select('id', 'mata_kuliah_id'))
            ->with('kelas:id,mata_kuliah_id')
            ->get()
            ->pluck('kelas.mata_kuliah_id')
            ->filter()
            ->unique()
            ->toArray();

        // Gabungan seluruh ID mata kuliah yang sudah pernah tersentuh oleh kelas ini
        $allTakenMkIds = array_values(array_unique(array_merge($paketMkIds, $krsMkIds)));

        // 3. Ambil seluruh kurikulum mata kuliah prodi rombel ini
        $kurikulumIds = Kurikulum::where('program_studi_id', $rombel->program_studi_id)
            ->pluck('id');

        $allCourses = MataKuliah::with(['kurikulum.programStudi'])
            ->whereIn('kurikulum_id', $kurikulumIds)
            ->where('is_active', true)
            ->orderBy('semester_anjuran')
            ->orderBy('kode_mk')
            ->get();

        $paketByMk = $paketRecords->keyBy('mata_kuliah_id');

        $sudahDiambil = [];
        $belumDiambil = [];

        foreach ($allCourses as $mk) {
            $isTaken = in_array($mk->id, $allTakenMkIds);
            if ($isTaken) {
                $pInfo = $paketByMk->get($mk->id);
                $isCurrent = false;
                if ($pInfo) {
                    $isCurrent = $taId ? ($pInfo->tahun_akademik_id === $taId || is_null($pInfo->tahun_akademik_id)) : true;
                }

                $sudahDiambil[] = [
                    'id' => $mk->id,
                    'kode_mk' => $mk->kode_mk,
                    'nama' => $mk->nama,
                    'total_sks' => $mk->total_sks ?? ($mk->sks_teori + $mk->sks_praktik),
                    'sks_teori' => $mk->sks_teori,
                    'sks_praktik' => $mk->sks_praktik,
                    'semester_anjuran' => $pInfo?->semester_anjuran ?? $mk->semester_anjuran,
                    'kurikulum' => $mk->kurikulum,
                    'is_wajib' => $pInfo ? (bool) $pInfo->is_wajib : true,
                    'is_current_paket' => $isCurrent,
                    'paket_id' => $pInfo?->id,
                    'nomor_sk' => $pInfo?->nomor_sk,
                    'tahun_akademik' => $pInfo?->tahunAkademik,
                    'sumber' => $pInfo ? 'paket_rombel' : 'krs_mahasiswa',
                ];
            } else {
                $belumDiambil[] = [
                    'id' => $mk->id,
                    'kode_mk' => $mk->kode_mk,
                    'nama' => $mk->nama,
                    'total_sks' => $mk->total_sks ?? ($mk->sks_teori + $mk->sks_praktik),
                    'sks_teori' => $mk->sks_teori,
                    'sks_praktik' => $mk->sks_praktik,
                    'semester_anjuran' => $mk->semester_anjuran,
                    'kategori' => $mk->kategori ?? 'Wajib',
                    'kurikulum' => $mk->kurikulum,
                    'is_wajib' => true,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar riwayat mata kuliah rombel berhasil dimuat',
            'data' => [
                'rombel' => $rombel,
                'nomor_sk' => $activeNomorSk,
                'sk_info' => $skInfo,
                'sudah_diambil' => $sudahDiambil,
                'belum_diambil' => $belumDiambil,
                'total_sudah_diambil' => count($sudahDiambil),
                'total_belum_diambil' => count($belumDiambil),
            ],
        ]);
    }

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

        $nomorSuratId = $request->input('nomor_surat_id');
        $nomorSk = $request->input('nomor_sk');
        if (!$nomorSuratId && $nomorSk) {
            $suratRef = \App\Models\Arsip\NomorSurat::where('nomor_surat', $nomorSk)->first();
            if ($suratRef) {
                $nomorSuratId = $suratRef->id;
            }
        }

        $item = PaketMkRombel::create(array_merge($kunci, [
            'nomor_surat_id' => $nomorSuratId,
            'nomor_sk' => $nomorSk,
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
            'nomor_sk' => 'required|string|max:150',
            'items' => 'required|array|min:1',
            'items.*.mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'items.*.semester_anjuran' => 'nullable|integer|min:1|max:14',
            'items.*.is_wajib' => 'nullable|boolean',
        ], [
            'nomor_sk.required' => 'Nomor SK wajib dilampirkan untuk menyimpan paket rombel.',
        ]);

        $nomorSk = trim((string) $validated['nomor_sk']);
        $surat = \App\Models\Arsip\NomorSurat::where('nomor_surat', $nomorSk)->first();
        if (!$surat) {
            return response()->json([
                'status' => 'error',
                'message' => "Nomor SK '{$nomorSk}' tidak terdaftar di modul Arsip.",
                'errors' => ['nomor_sk' => ["Nomor SK '{$nomorSk}' tidak terdaftar di modul Arsip."]],
            ], 422);
        }

        if ($surat->status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => "Nomor SK '{$nomorSk}' berstatus DIBATALKAN di modul Arsip.",
                'errors' => ['nomor_sk' => ["Nomor SK '{$nomorSk}' berstatus DIBATALKAN."]],
            ], 422);
        }

        if (!$surat->has_dokumen) {
            return response()->json([
                'status' => 'error',
                'message' => "Nomor SK '{$nomorSk}' terdaftar di Arsip, tetapi berkas fisik dokumen SK belum diunggah (dokumen masih kosong).",
                'errors' => ['nomor_sk' => ["Nomor SK '{$nomorSk}' terdaftar di Arsip, tetapi berkas fisik dokumen SK belum diunggah."]],
            ], 422);
        }

        $taId = $validated['tahun_akademik_id'] ?? null;
        return DB::transaction(function () use ($validated, $taId, $nomorSk, $surat, $request) {
            $keepIds = [];
            foreach ($validated['items'] as $row) {
                $item = PaketMkRombel::updateOrCreate(
                    [
                        'master_kelas_id' => $validated['master_kelas_id'],
                        'mata_kuliah_id' => $row['mata_kuliah_id'],
                        'tahun_akademik_id' => $taId,
                    ],
                    [
                        'nomor_surat_id' => $surat->id,
                        'nomor_sk' => $surat->nomor_surat,
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

            $data = PaketMkRombel::with(['mataKuliah', 'nomorSurat'])
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
                    newValues: [
                        'master_kelas_id' => $validated['master_kelas_id'],
                        'nomor_sk' => $nomorSk,
                        'total_item' => $data->count(),
                    ],
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log bulkSync paket: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => "Paket rombel disinkronkan ({$data->count()} MK, {$deleted} dihapus) dengan SK '{$nomorSk}'",
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
                'rombel' => $rombel instanceof \Illuminate\Database\Eloquent\Model ? $rombel->load('programStudi') : $rombel,
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
