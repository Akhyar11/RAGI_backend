<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\TugasAkhir\ApproveProposalTaRequest;
use App\Http\Requests\Siakad\TugasAkhir\FinalizeTaSidangRequest;
use App\Http\Requests\Siakad\TugasAkhir\StoreTaBimbinganRequest;
use App\Http\Requests\Siakad\TugasAkhir\StoreTaNilaiRequest;
use App\Http\Requests\Siakad\TugasAkhir\StoreTaSidangRequest;
use App\Http\Requests\Siakad\TugasAkhir\StoreTugasAkhirRequest;
use App\Http\Requests\Siakad\TugasAkhir\VerifyTaBimbinganRequest;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\TugasAkhir;
use App\Models\Siakad\TugasAkhirBimbingan;
use App\Models\Siakad\TugasAkhirNilai;
use App\Models\Siakad\TugasAkhirPenguji;
use App\Models\Siakad\TugasAkhirSidang;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TugasAkhirController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * Menampilkan daftar Tugas Akhir dengan filter dan isolasi
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('siakad.ta.read');

        $user = $request->user();
        $query = TugasAkhir::with([
            'mahasiswa.programStudi',
            'tahunAkademik',
            'programStudi',
            'dosenPembimbing1.user',
            'dosenPembimbing2.user',
        ]);

        $canManage = $user->can('siakad.ta.manage');

        // Isolasi Mahasiswa
        $mahasiswa = Mahasiswa::where('user_id', $user?->id)->first();
        if ($mahasiswa && !$canManage) {
            $query->where('mahasiswa_id', $mahasiswa->id);
        }

        // Filter / Isolasi Dosen Pembimbing
        $dosen = Dosen::where('user_id', $user?->id)->first();
        if ($dosen && !$canManage && !$mahasiswa) {
            if ($request->boolean('my_advisees_only', true)) {
                $query->where(function ($q) use ($dosen) {
                    $q->where('dosen_pembimbing_1_id', $dosen->id)
                        ->orWhere('dosen_pembimbing_2_id', $dosen->id);
                });
            }
        }

        // Filter prodi
        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->integer('program_studi_id'));
        }

        // Filter tahap & status
        if ($request->filled('tahap')) {
            $query->where('tahap', $request->string('tahap'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Search judul / mahasiswa
        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(function ($q) use ($s) {
                $q->where('judul_utama', 'like', "%{$s}%")
                    ->orWhere('judul_inggris', 'like', "%{$s}%")
                    ->orWhere('bidang_kajian', 'like', "%{$s}%")
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
            'message' => 'Daftar Tugas Akhir berhasil dimuat.',
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
     * Detail proposal / siklus Tugas Akhir
     */
    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.ta.read');

        $ta = TugasAkhir::with([
            'mahasiswa.programStudi',
            'tahunAkademik',
            'programStudi',
            'dosenPembimbing1.user',
            'dosenPembimbing2.user',
            'bimbingans.dosen.user',
            'sidangs.ruangan.gedung',
            'sidangs.pengujis.dosen.user',
            'sidangs.pengujis.nilai',
        ])->findOrFail($id);

        $this->authorizeTaAccess($request->user(), $ta);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Tugas Akhir berhasil dimuat.',
            'data' => $ta,
        ]);
    }

    /**
     * Pengajuan proposal judul Tugas Akhir oleh Mahasiswa
     */
    public function store(StoreTugasAkhirRequest $request): JsonResponse
    {
        Gate::authorize('siakad.ta.create');

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
                'message' => 'Data mahasiswa tidak ditemukan untuk pengajuan Tugas Akhir.',
            ], 400);
        }

        $berkasProposalPath = null;
        if ($request->hasFile('berkas_proposal')) {
            $berkasProposalPath = $this->fileStorageService->store(
                $request->file('berkas_proposal'),
                'siakad/ta/proposal',
                private: true
            );
        }

        $ta = TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $validated['tahun_akademik_id'],
            'program_studi_id' => $validated['program_studi_id'] ?? $mahasiswa->program_studi_id,
            'judul_utama' => $validated['judul_utama'],
            'judul_inggris' => $validated['judul_inggris'] ?? null,
            'bidang_kajian' => $validated['bidang_kajian'] ?? null,
            'abstrak' => $validated['abstrak'] ?? null,
            'dosen_pembimbing_1_id' => $validated['usulan_pembimbing_1_id'] ?? null,
            'dosen_pembimbing_2_id' => $validated['usulan_pembimbing_2_id'] ?? null,
            'tahap' => TugasAkhir::TAHAP_PROPOSAL,
            'status' => TugasAkhir::STATUS_DIAJUKAN,
            'berkas_proposal' => $berkasProposalPath,
        ]);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_tugas_akhir',
                recordId: $ta->id,
                oldValues: [],
                newValues: $ta->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta create: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan proposal Tugas Akhir berhasil disimpan dan menunggu verifikasi Program Studi.',
            'data' => [
                'id' => $ta->id,
                'status' => $ta->status,
                'judul_utama' => $ta->judul_utama,
            ],
        ], 201);
    }

    /**
     * Approval proposal & penetapan Dosen Pembimbing oleh Kaprodi/Admin
     */
    public function approveProposal(ApproveProposalTaRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.ta.manage');

        $ta = TugasAkhir::findOrFail($id);
        $this->authorizeTaAccess($request->user(), $ta);

        $oldValues = $ta->getOriginal();
        $validated = $request->validated();

        $ta->status = $validated['status'] ?? TugasAkhir::STATUS_DISETUJUI_PRODI;
        $ta->dosen_pembimbing_1_id = $validated['dosen_pembimbing_1_id'];
        if (isset($validated['dosen_pembimbing_2_id'])) {
            $ta->dosen_pembimbing_2_id = $validated['dosen_pembimbing_2_id'];
        }
        if (isset($validated['nomor_sk_pembimbing'])) {
            $ta->nomor_sk_pembimbing = $validated['nomor_sk_pembimbing'];
        }
        if (isset($validated['tanggal_sk_pembimbing'])) {
            $ta->tanggal_sk_pembimbing = $validated['tanggal_sk_pembimbing'];
        }
        if (isset($validated['catatan_prodi'])) {
            $ta->catatan_prodi = $validated['catatan_prodi'];
        }

        $ta->save();
        $newValues = $ta->getChanges();
        $oldValues = array_intersect_key($oldValues, $newValues);

        $taAuditAction = $ta->status === TugasAkhir::STATUS_DITOLAK ? 'reject' : 'approve';
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $taAuditAction,
                tableName: 'siakad_tugas_akhir',
                recordId: $ta->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta approve proposal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Proposal Tugas Akhir disetujui dan pembimbing berhasil ditetapkan.',
            'data' => $ta->load(['dosenPembimbing1', 'dosenPembimbing2', 'mahasiswa']),
        ]);
    }

    /**
     * Input catatan asistensi bimbingan skripsi oleh Mahasiswa
     */
    public function storeBimbingan(StoreTaBimbinganRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.ta.create');

        $ta = TugasAkhir::findOrFail($id);
        $this->authorizeTaAccess($request->user(), $ta);

        $validated = $request->validated();

        $draftPath = null;
        if ($request->hasFile('berkas_draft')) {
            $draftPath = $this->fileStorageService->store(
                $request->file('berkas_draft'),
                'siakad/ta/bimbingan',
                private: true
            );
        }

        $bimbingan = TugasAkhirBimbingan::create([
            'tugas_akhir_id' => $ta->id,
            'dosen_id' => $validated['dosen_id'],
            'tanggal' => $validated['tanggal'],
            'bab_pembahasan' => $validated['bab_pembahasan'],
            'uraian_konsultasi' => $validated['uraian_konsultasi'],
            'arahan_revisi' => $validated['arahan_revisi'] ?? null,
            'berkas_draft' => $draftPath,
            'status_validasi' => TugasAkhirBimbingan::STATUS_MENUNGGU,
        ]);

        $ta->increment('total_sesi_bimbingan');

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: 'create',
                tableName: 'siakad_ta_bimbingan',
                recordId: $bimbingan->id,
                oldValues: [],
                newValues: $bimbingan->toArray(),
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta bimbingan: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Log asistensi bimbingan berhasil dicatat.',
            'data' => $bimbingan->load('dosen'),
        ], 201);
    }

    /**
     * Verifikasi logbook asistensi skripsi oleh Dosen Pembimbing
     */
    public function verifyBimbingan(VerifyTaBimbinganRequest $request, int $id, int $bimbinganId): JsonResponse
    {
        Gate::authorize('siakad.ta.manage');

        $ta = TugasAkhir::findOrFail($id);
        $this->authorizeTaAccess($request->user(), $ta);

        $bimbingan = TugasAkhirBimbingan::where('tugas_akhir_id', $ta->id)->findOrFail($bimbinganId);
        $oldValues = $bimbingan->getOriginal();
        $validated = $request->validated();

        $bimbingan->status_validasi = $validated['status_validasi'];
        if (isset($validated['arahan_revisi'])) {
            $bimbingan->arahan_revisi = $validated['arahan_revisi'];
        }
        $bimbingan->save();
        $newValues = $bimbingan->getChanges();

        // Update status kesiapan sidang bila minimal 8 logbook di-ACC
        $accCount = TugasAkhirBimbingan::where('tugas_akhir_id', $ta->id)
            ->where('status_validasi', TugasAkhirBimbingan::STATUS_ACC)
            ->count();
        if ($accCount >= 8 && in_array($ta->status, [TugasAkhir::STATUS_SEDANG_BIMBINGAN, TugasAkhir::STATUS_DISETUJUI_PRODI])) {
            $taOld = $ta->getOriginal();
            $ta->status = TugasAkhir::STATUS_SIAP_SIDANG;
            $ta->save();
            $taNew = $ta->getChanges();

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_tugas_akhir',
                    recordId: $ta->id,
                    oldValues: array_intersect_key($taOld, $taNew),
                    newValues: $taNew,
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log status siap sidang TA: ' . $e->getMessage());
            }
        }

        $bimbTaAudit = $bimbingan->status_validasi === TugasAkhirBimbingan::STATUS_REVISI ? 'reject' : 'approve';
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $bimbTaAudit,
                tableName: 'siakad_ta_bimbingan',
                recordId: $bimbingan->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta verify bimbingan: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status asistensi bimbingan berhasil diperbarui.',
            'data' => $bimbingan,
        ]);
    }

    /**
     * Penjadwalan Sidang & Majelis Penguji
     */
    public function storeSidang(StoreTaSidangRequest $request, int $id): JsonResponse
    {
        Gate::authorize('siakad.ta.manage');

        $ta = TugasAkhir::findOrFail($id);
        $this->authorizeTaAccess($request->user(), $ta);

        $validated = $request->validated();

        return DB::transaction(function () use ($ta, $validated, $request) {
            $sidang = TugasAkhirSidang::create([
                'tugas_akhir_id' => $ta->id,
                'jenis_sidang' => $validated['jenis_sidang'],
                'periode_sidang' => $validated['periode_sidang'] ?? null,
                'tanggal_sidang' => $validated['tanggal_sidang'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruangan_id' => $validated['ruangan_id'] ?? null,
                'ruangan_nama_manual' => $validated['ruangan_nama_manual'] ?? null,
                'ketua_sidang_id' => $validated['ketua_sidang_id'] ?? null,
                'sekretaris_sidang_id' => $validated['sekretaris_sidang_id'] ?? null,
                'catatan_sidang' => $validated['catatan_sidang'] ?? null,
                'status_kelulusan' => TugasAkhirSidang::STATUS_BELUM_SIDANG,
            ]);

            foreach ($validated['penguji'] as $p) {
                TugasAkhirPenguji::create([
                    'sidang_id' => $sidang->id,
                    'dosen_id' => $p['dosen_id'],
                    'peran_penguji' => $p['peran_penguji'],
                    'kehadiran' => TugasAkhirPenguji::HADIR,
                ]);
            }

            // Sync tahap TA
            $taOldValues = $ta->getOriginal();
            if ($validated['jenis_sidang'] === TugasAkhirSidang::JENIS_SIDANG_SKRIPSI) {
                $ta->tahap = TugasAkhir::TAHAP_SIDANG_SKRIPSI;
            } elseif ($validated['jenis_sidang'] === TugasAkhirSidang::JENIS_SEMINAR_HASIL) {
                $ta->tahap = TugasAkhir::TAHAP_SEMINAR_HASIL;
            } else {
                $ta->tahap = TugasAkhir::TAHAP_PROPOSAL;
            }
            $ta->save();
            $taNewValues = $ta->getChanges();
            $taOldValues = array_intersect_key($taOldValues, $taNewValues);

            if (!empty($taNewValues)) {
                try {
                    AuditLogService::record(
                        module: 'SIAKAD',
                        action: 'update',
                        tableName: 'siakad_tugas_akhir',
                        recordId: $ta->id,
                        oldValues: $taOldValues,
                        newValues: $taNewValues,
                        request: $request
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mencatat audit log ta sync tahap sidang: ' . $e->getMessage());
                }
            }

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'create',
                    tableName: 'siakad_ta_sidang',
                    recordId: $sidang->id,
                    oldValues: [],
                    newValues: $sidang->toArray(),
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log ta sidang: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal sidang dan majelis penguji berhasil ditetapkan.',
                'data' => $sidang->load(['pengujis.dosen', 'ruangan']),
            ], 201);
        });
    }

    /**
     * Input nilai individual sidang oleh Dosen Penguji
     */
    public function storeNilaiSidang(StoreTaNilaiRequest $request, int $sidangId): JsonResponse
    {
        Gate::authorize('siakad.ta.manage');

        $sidang = TugasAkhirSidang::findOrFail($sidangId);
        $ta = TugasAkhir::findOrFail($sidang->tugas_akhir_id);
        $this->authorizeTaAccess($request->user(), $ta);

        if ($sidang->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sidang telah difinalisasi dan penilaian terkunci.',
            ], 400);
        }

        $user = $request->user();
        $validated = $request->validated();

        $dosen = Dosen::where('user_id', $user?->id)->first();
        if (!$user->can('siakad.ta.manage')) {
            if (!$dosen) {
                abort(403, 'Hanya dosen penguji yang dapat memberikan nilai sidang.');
            }
            $dosenId = $dosen->id;
        } else {
            $dosenId = $validated['dosen_id'] ?? $dosen?->id;
        }

        $penguji = TugasAkhirPenguji::where('sidang_id', $sidang->id)
            ->where('dosen_id', $dosenId)
            ->firstOrFail();

        $isNew = !$nilai = TugasAkhirNilai::where('penguji_id', $penguji->id)->first();
        if ($isNew) {
            $nilai = new TugasAkhirNilai(['penguji_id' => $penguji->id]);
            $oldValues = [];
        } else {
            $oldValues = $nilai->getOriginal();
        }

        $metodologi = (float) $validated['skor_metodologi'];
        $materi = (float) $validated['skor_materi_skripsi'];
        $presentasi = (float) $validated['skor_presentasi'];
        $penguasaan = (float) $validated['skor_penguasaan_materi'];

        // Komposit skor total: rata-rata 4 komponen rubrik
        $skorTotal = ($metodologi + $materi + $presentasi + $penguasaan) / 4;

        $nilai->skor_metodologi = $metodologi;
        $nilai->skor_materi_skripsi = $materi;
        $nilai->skor_presentasi = $presentasi;
        $nilai->skor_penguasaan_materi = $penguasaan;
        $nilai->skor_total = round($skorTotal, 2);
        $nilai->catatan_revisi = $validated['catatan_revisi'] ?? null;
        $nilai->is_submitted = true;
        $nilai->submitted_at = now();
        $nilai->save();

        $newValues = $isNew ? $nilai->toArray() : $nilai->getChanges();
        $oldValues = $isNew ? [] : array_intersect_key($oldValues, $newValues);

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $isNew ? 'create' : 'update',
                tableName: 'siakad_ta_nilai',
                recordId: $nilai->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta nilai: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penilaian penguji sidang berhasil disimpan.',
            'data' => $nilai,
        ]);
    }

    /**
     * Finalisasi hasil sidang skripsi & konversi komposit nilai akhir
     */
    public function finalizeSidang(FinalizeTaSidangRequest $request, int $sidangId): JsonResponse
    {
        Gate::authorize('siakad.ta.manage');

        $sidang = TugasAkhirSidang::with('pengujis.nilai')->findOrFail($sidangId);
        $ta = TugasAkhir::findOrFail($sidang->tugas_akhir_id);
        $this->authorizeTaAccess($request->user(), $ta);

        $oldValues = $sidang->getOriginal();
        $validated = $request->validated();

        if ($request->hasFile('berkas_bap')) {
            $sidang->berkas_bap = $this->fileStorageService->store(
                $request->file('berkas_bap'),
                'siakad/ta/bap',
                private: true
            );
        }

        $sidang->status_kelulusan = $validated['status_kelulusan'];
        $sidang->batas_waktu_revisi = $validated['batas_waktu_revisi'] ?? null;
        if (isset($validated['catatan_sidang'])) {
            $sidang->catatan_sidang = $validated['catatan_sidang'];
        }
        $sidang->is_locked = true;
        $sidang->save();
        $taOldValues = $ta->getOriginal();

        // Hitung komposit nilai akhir dari seluruh penguji
        $submittedNilai = $sidang->pengujis->map(fn($p) => $p->nilai)->filter(fn($n) => $n && $n->is_submitted);
        if ($submittedNilai->isNotEmpty()) {
            $avgSkor = $submittedNilai->avg('skor_total');
            $ta->nilai_angka_akhir = round($avgSkor, 2);

            $skala = SkalaNilai::konversiNilai($ta->nilai_angka_akhir, $ta->program_studi_id);
            if (!$skala) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Skala nilai untuk program studi ini belum dikonfigurasi di Master Skala Nilai.',
                ], 400);
            }
            $ta->nilai_huruf_akhir = $skala->nilai_huruf;
            $ta->bobot_mutu_akhir = (float) $skala->bobot_indeks;
        }

        if (in_array($validated['status_kelulusan'], [TugasAkhirSidang::STATUS_LULUS_REVISI_RINGAN, TugasAkhirSidang::STATUS_LULUS_REVISI_BERAT])) {
            $ta->status = TugasAkhir::STATUS_REVISI;
        } elseif ($validated['status_kelulusan'] === TugasAkhirSidang::STATUS_LULUS_TANPA_REVISI) {
            $ta->status = TugasAkhir::STATUS_SELESAI;
            $ta->tahap = TugasAkhir::TAHAP_LULUS;
        } else {
            $ta->status = TugasAkhir::STATUS_SEDANG_BIMBINGAN;
        }

        $ta->save();
        $taNewValues = $ta->getChanges();
        $taOldValues = array_intersect_key($taOldValues, $taNewValues);

        if (!empty($taNewValues)) {
            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_tugas_akhir',
                    recordId: $ta->id,
                    oldValues: $taOldValues,
                    newValues: $taNewValues,
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log update nilai akhir TA: ' . $e->getMessage());
            }
        }

        $newValues = $sidang->getChanges();
        $oldValues = array_intersect_key($oldValues, $newValues);

        $sidangAudit = $sidang->status_kelulusan === TugasAkhirSidang::STATUS_MENGULANG ? 'reject' : 'approve';
        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $sidangAudit,
                tableName: 'siakad_ta_sidang',
                recordId: $sidang->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ta finalize sidang: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sidang Tugas Akhir berhasil difinalisasi dan nilai akhir telah dikompositkan.',
            'data' => [
                'sidang' => $sidang,
                'tugas_akhir' => $ta,
            ],
        ]);
    }

    /**
     * Memeriksa otorisasi akses Tugas Akhir
     */
    protected function authorizeTaAccess($user, TugasAkhir $ta): void
    {
        if ($user->can('siakad.ta.manage')) {
            return;
        }

        $mahasiswa = Mahasiswa::where('user_id', $user->id)->first();
        if ($mahasiswa && $ta->mahasiswa_id === $mahasiswa->id) {
            return;
        }

        $dosen = Dosen::where('user_id', $user->id)->first();
        if ($dosen && ($ta->dosen_pembimbing_1_id === $dosen->id || $ta->dosen_pembimbing_2_id === $dosen->id)) {
            return;
        }

        // Dosen penguji pada salah satu sidang TA
        if ($dosen) {
            $isPenguji = TugasAkhirPenguji::whereHas('sidang', fn($q) => $q->where('tugas_akhir_id', $ta->id))
                ->where('dosen_id', $dosen->id)
                ->exists();
            if ($isPenguji) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki hak akses pada berkas Tugas Akhir ini.');
    }
}
