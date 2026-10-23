<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Pkl\StorePklBimbinganRequest;
use App\Http\Requests\Siakad\Pkl\StorePklNilaiRequest;
use App\Http\Requests\Siakad\Pkl\StorePklRequest;
use App\Http\Requests\Siakad\Pkl\VerifyPklBimbinganRequest;
use App\Http\Requests\Siakad\Pkl\VerifyPklRequest;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Pkl;
use App\Models\Siakad\PklBimbingan;
use App\Models\Siakad\PklPenilaian;
use App\Models\Siakad\SkalaNilai;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PklController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * Mengambil daftar PKL dengan filter dan isolasi hak akses
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('siakad.pkl.read');

        $user = $request->user();
        $query = Pkl::with(['mahasiswa.programStudi', 'tahunAkademik', 'programStudi', 'dosenPembimbing.user', 'penilaian']);

        $canManage = $user->can('siakad.pkl.manage');

        // Isolasi Mahasiswa
        $mahasiswa = Mahasiswa::where('user_id', $user?->id)->first();
        if ($mahasiswa && !$canManage) {
            $query->where('mahasiswa_id', $mahasiswa->id);
        }

        // Filter / Isolasi Dosen
        $dosen = Dosen::where('user_id', $user?->id)->first();
        if ($dosen && !$canManage && !$mahasiswa) {
            if ($request->boolean('my_advisees_only', true)) {
                $query->where('dosen_pembimbing_id', $dosen->id);
            }
        }

        // Filter prodi
        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->integer('program_studi_id'));
        }

        // Filter tahun akademik
        if ($request->filled('tahun_akademik_id')) {
            $query->where('tahun_akademik_id', $request->integer('tahun_akademik_id'));
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Search instansi / mahasiswa
        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(function ($q) use ($s) {
                $q->where('instansi_nama', 'like', "%{$s}%")
                    ->orWhere('judul_laporan', 'like', "%{$s}%")
                    ->orWhereHas('mahasiswa', function ($mq) use ($s) {
                        $mq->where('nama_lengkap', 'like', "%{$s}%")
                            ->orWhere('nim', 'like', "%{$s}%");
                    });
            });
        }

        $sortOrder = $request->string('sort_order')->lower() === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortOrder);

        $perPage = min(100, $request->integer('per_page', 15));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan PKL berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Detail pengajuan PKL
     */
    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.pkl.read');

        $pkl = Pkl::with([
            'mahasiswa.programStudi',
            'tahunAkademik',
            'programStudi',
            'dosenPembimbing.user',
            'bimbingans',
            'penilaian.penilaiDosen.user'
        ])->findOrFail($id);

        $this->authorizePklAccess($request->user(), $pkl);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail PKL berhasil dimuat.',
            'data' => $pkl,
        ]);
    }

    /**
     * Pendaftaran / Pengajuan PKL Mandiri
     */
    public function store(StorePklRequest $request): JsonResponse
    {
        Gate::authorize('siakad.pkl.create');

        $user = $request->user();
        $validated = $request->validated();

        $mahasiswaId = $validated['mahasiswa_id'] ?? null;
        if (!$mahasiswaId) {
            $mahasiswa = Mahasiswa::where('user_id', $user?->id)->first();
            $mahasiswaId = $mahasiswa?->id;
        } else {
            $mahasiswa = Mahasiswa::findOrFail($mahasiswaId);
        }

        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan untuk pengajuan PKL.',
            ], 400);
        }

        $berkasPermohonanPath = null;
        if ($request->hasFile('berkas_permohonan')) {
            $berkasPermohonanPath = $this->fileStorageService->store(
                $request->file('berkas_permohonan'),
                'siakad/pkl/permohonan',
                private: true
            );
        }

        $pkl = Pkl::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $validated['tahun_akademik_id'],
            'program_studi_id' => $validated['program_studi_id'] ?? $mahasiswa->program_studi_id,
            'jenis' => $validated['jenis'],
            'judul_laporan' => $validated['judul_laporan'] ?? null,
            'instansi_nama' => $validated['instansi_nama'],
            'instansi_alamat' => $validated['instansi_alamat'] ?? null,
            'instansi_kota' => $validated['instansi_kota'] ?? null,
            'pembimbing_lapangan_nama' => $validated['pembimbing_lapangan_nama'] ?? null,
            'pembimbing_lapangan_kontak' => $validated['pembimbing_lapangan_kontak'] ?? null,
            'pembimbing_lapangan_jabatan' => $validated['pembimbing_lapangan_jabatan'] ?? null,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'status' => Pkl::STATUS_DIAJUKAN,
            'berkas_permohonan' => $berkasPermohonanPath,
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pkl',
                recordId: $pkl->id,
                oldValues: [],
                newValues: $pkl->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pkl create: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan PKL berhasil disimpan dan menunggu persetujuan Program Studi.',
            'data' => [
                'id' => $pkl->id,
                'status' => $pkl->status,
                'instansi_nama' => $pkl->instansi_nama,
            ],
        ], 201);
    }

    /**
     * Verifikasi / Approval PKL oleh Kaprodi atau Admin
     */
    public function verify(VerifyPklRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.pkl.manage');

        $pkl = Pkl::findOrFail($id);
        $this->authorizePklAccess($request->user(), $pkl);

        $oldValues = $pkl->getOriginal();
        $validated = $request->validated();

        $pkl->status = $validated['status'];
        if (isset($validated['dosen_pembimbing_id'])) {
            $pkl->dosen_pembimbing_id = $validated['dosen_pembimbing_id'];
        }
        if (isset($validated['catatan_penolakan'])) {
            $pkl->catatan_penolakan = $validated['catatan_penolakan'];
        }

        $pkl->save();
        $newValues = $pkl->getChanges();
        $oldValues = array_intersect_key($oldValues, $newValues);

        $auditAction = $pkl->status === Pkl::STATUS_DITOLAK ? 'reject' : 'approve';
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $auditAction,
                tableName: 'siakad_pkl',
                recordId: $pkl->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pkl verify: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan PKL berhasil diperbarui.',
            'data' => $pkl->load(['mahasiswa', 'dosenPembimbing']),
        ]);
    }

    /**
     * Menambahkan logbook bimbingan harian PKL
     */
    public function storeBimbingan(StorePklBimbinganRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.pkl.create');

        $pkl = Pkl::findOrFail($id);
        $this->authorizePklAccess($request->user(), $pkl);

        $validated = $request->validated();

        $filePath = null;
        if ($request->hasFile('file_lampiran')) {
            $filePath = $this->fileStorageService->store(
                $request->file('file_lampiran'),
                'siakad/pkl/bimbingan',
                private: true
            );
        }

        $bimbingan = PklBimbingan::create([
            'pkl_id' => $pkl->id,
            'tanggal' => $validated['tanggal'],
            'aktivitas' => $validated['aktivitas'],
            'target_capaian' => $validated['target_capaian'] ?? null,
            'kendala' => $validated['kendala'] ?? null,
            'file_lampiran' => $filePath,
            'status_verifikasi' => PklBimbingan::STATUS_PENDING,
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_pkl_bimbingan',
                recordId: $bimbingan->id,
                oldValues: [],
                newValues: $bimbingan->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pkl bimbingan: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logbook aktivitas PKL berhasil disimpan.',
            'data' => $bimbingan,
        ], 201);
    }

    /**
     * Verifikasi logbook PKL oleh dosen pembimbing
     */
    public function verifyBimbingan(VerifyPklBimbinganRequest $request, int $id, int $bimbinganId): JsonResponse
    {
        Gate::authorize('siakad.pkl.manage');

        $pkl = Pkl::findOrFail($id);
        $this->authorizePklAccess($request->user(), $pkl);

        $bimbingan = PklBimbingan::where('pkl_id', $pkl->id)->findOrFail($bimbinganId);
        $oldValues = $bimbingan->getOriginal();
        $validated = $request->validated();

        $bimbingan->status_verifikasi = $validated['status_verifikasi'];
        $bimbingan->catatan_dosen = $validated['catatan_dosen'] ?? null;
        $bimbingan->diverifikasi_oleh_dosen_at = now();
        $bimbingan->save();

        $newValues = $bimbingan->getChanges();

        $bimbAuditAction = $bimbingan->status_verifikasi === PklBimbingan::STATUS_REVISI ? 'reject' : 'approve';
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $bimbAuditAction,
                tableName: 'siakad_pkl_bimbingan',
                recordId: $bimbingan->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pkl verify bimbingan: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi logbook PKL berhasil diperbarui.',
            'data' => $bimbingan,
        ]);
    }

    /**
     * Input penilaian komprehensif PKL (Lapangan & Dosen)
     */
    public function storeNilai(StorePklNilaiRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.pkl.manage');

        $pkl = Pkl::findOrFail($id);
        $user = $request->user();
        $dosen = Dosen::where('user_id', $user?->id)->first();
        if (!$user->can('siakad.pkl.manage')) {
            if (!$dosen || (int) $pkl->dosen_pembimbing_id !== (int) $dosen->id) {
                abort(403, 'Hanya dosen pembimbing atau pengelola prodi yang berhak menginput penilaian PKL.');
            }
        }

        $isNew = !$penilaian = PklPenilaian::where('pkl_id', $pkl->id)->first();
        if ($isNew) {
            $penilaian = new PklPenilaian(['pkl_id' => $pkl->id]);
            $oldValues = [];
        } else {
            $oldValues = $penilaian->getOriginal();
        }

        if ($penilaian->is_finalized && !$user->can('siakad.pkl.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Penilaian PKL telah terkunci/final dan tidak dapat diubah.',
            ], 400);
        }

        $validated = $request->validated();

        if ($request->hasFile('berkas_nilai_lapangan')) {
            $penilaian->berkas_nilai_lapangan = $this->fileStorageService->store(
                $request->file('berkas_nilai_lapangan'),
                'siakad/pkl/nilai_lapangan',
                private: true
            );
        }

        if ($dosen) {
            $penilaian->penilai_dosen_id = $dosen->id;
        }

        // Sub-nilai lapangan
        if (isset($validated['nilai_lapangan_kedisiplinan'])) $penilaian->nilai_lapangan_kedisiplinan = $validated['nilai_lapangan_kedisiplinan'];
        if (isset($validated['nilai_lapangan_kinerja'])) $penilaian->nilai_lapangan_kinerja = $validated['nilai_lapangan_kinerja'];
        if (isset($validated['nilai_lapangan_kemampuan_teknis'])) $penilaian->nilai_lapangan_kemampuan_teknis = $validated['nilai_lapangan_kemampuan_teknis'];
        if (isset($validated['nilai_lapangan_inisiatif'])) $penilaian->nilai_lapangan_inisiatif = $validated['nilai_lapangan_inisiatif'];
        if (isset($validated['catatan_lapangan'])) $penilaian->catatan_lapangan = $validated['catatan_lapangan'];

        $nilaiLapTotal = isset($validated['nilai_lapangan_total'])
            ? (float) $validated['nilai_lapangan_total']
            : (($penilaian->nilai_lapangan_kedisiplinan + $penilaian->nilai_lapangan_kinerja + $penilaian->nilai_lapangan_kemampuan_teknis + $penilaian->nilai_lapangan_inisiatif) / 4);
        $penilaian->nilai_lapangan_total = $nilaiLapTotal;

        // Sub-nilai dosen
        if (isset($validated['nilai_dosen_bimbingan'])) $penilaian->nilai_dosen_bimbingan = $validated['nilai_dosen_bimbingan'];
        if (isset($validated['nilai_dosen_laporan'])) $penilaian->nilai_dosen_laporan = $validated['nilai_dosen_laporan'];
        if (isset($validated['nilai_dosen_presentasi'])) $penilaian->nilai_dosen_presentasi = $validated['nilai_dosen_presentasi'];
        if (isset($validated['catatan_dosen'])) $penilaian->catatan_dosen = $validated['catatan_dosen'];

        $nilaiDosenTotal = isset($validated['nilai_dosen_total'])
            ? (float) $validated['nilai_dosen_total']
            : (($penilaian->nilai_dosen_bimbingan + $penilaian->nilai_dosen_laporan + $penilaian->nilai_dosen_presentasi) / 3);
        $penilaian->nilai_dosen_total = $nilaiDosenTotal;

        $bobotLap = (float) ($validated['bobot_lapangan_persen'] ?? $penilaian->bobot_lapangan_persen ?? 40.00);
        $bobotDsn = (float) ($validated['bobot_dosen_persen'] ?? $penilaian->bobot_dosen_persen ?? 60.00);
        $penilaian->bobot_lapangan_persen = $bobotLap;
        $penilaian->bobot_dosen_persen = $bobotDsn;

        // Kalkulasi skor akhir tertimbang
        $nilaiAngkaAkhir = ($nilaiLapTotal * ($bobotLap / 100)) + ($nilaiDosenTotal * ($bobotDsn / 100));
        $penilaian->nilai_angka_akhir = round($nilaiAngkaAkhir, 2);

        // Lookup konversi mutu & huruf melalui SkalaNilai prodi (Zero Hardcode)
        $skala = SkalaNilai::konversiNilai($penilaian->nilai_angka_akhir, $pkl->program_studi_id);
        if (!$skala) {
            return response()->json([
                'status' => 'error',
                'message' => 'Skala nilai untuk program studi ini belum dikonfigurasi di Master Skala Nilai.',
            ], 400);
        }
        $penilaian->nilai_huruf_akhir = $skala->nilai_huruf;
        $penilaian->bobot_mutu_akhir = (float) $skala->bobot_indeks;

        if ($request->boolean('is_finalized')) {
            $penilaian->is_finalized = true;
            $penilaian->finalized_at = now();
            $penilaian->finalized_by = $user?->id;
            
            $pklOldValues = $pkl->getOriginal();
            $pkl->status = Pkl::STATUS_DINILAI;
            $pkl->save();
            $pklNewValues = $pkl->getChanges();

            $pklOldValues = array_intersect_key($pklOldValues, $pklNewValues);
            if (!empty($pklNewValues)) {
                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: 'update',
                        tableName: 'siakad_pkl',
                        recordId: $pkl->id,
                        oldValues: $pklOldValues,
                        newValues: $pklNewValues,
                        request: $request
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mencatat audit log status pkl dinilai: ' . $e->getMessage());
                }
            }
        }

        $penilaian->save();
        $newValues = $isNew ? $penilaian->toArray() : $penilaian->getChanges();
        $oldValues = $isNew ? [] : array_intersect_key($oldValues, $newValues);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $isNew ? 'create' : 'update',
                tableName: 'siakad_pkl_penilaian',
                recordId: $penilaian->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log pkl nilai: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penilaian PKL berhasil disimpan.',
            'data' => $penilaian,
        ]);
    }

    /**
     * Memeriksa otorisasi akses PKL sesuai peran pengguna
     */
    protected function authorizePklAccess($user, Pkl $pkl): void
    {
        if ($user->can('siakad.pkl.manage')) {
            return;
        }

        $mahasiswa = Mahasiswa::where('user_id', $user->id)->first();
        if ($mahasiswa && $pkl->mahasiswa_id === $mahasiswa->id) {
            return;
        }

        $dosen = Dosen::where('user_id', $user->id)->first();
        if ($dosen && $pkl->dosen_pembimbing_id === $dosen->id) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses pada berkas PKL ini.');
    }
}
