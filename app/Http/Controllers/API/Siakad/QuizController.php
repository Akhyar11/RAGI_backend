<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siakad\Lms\AttachQuizSoalRequest;
use App\Http\Requests\Siakad\Lms\AutosaveQuizRequest;
use App\Http\Requests\Siakad\Lms\AddTryoutPesertaRequest;
use App\Http\Requests\Siakad\Lms\ManualQuizGradeRequest;
use App\Http\Requests\Siakad\Lms\StartAttemptRequest;
use App\Http\Requests\Siakad\Lms\StoreQuizRequest;
use App\Http\Requests\Siakad\Lms\UpdateQuizRequest;
use App\Models\Lms\Quiz;
use App\Models\Lms\QuizAttempt;
use App\Models\Lms\QuizAttemptJawaban;
use App\Models\Lms\QuizKolaborator;
use App\Models\Lms\QuizSoal;
use App\Models\Lms\TryoutPeserta;
use App\Services\AuditLogService;
use App\Services\Siakad\LmsService;
use App\Services\Siakad\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function __construct(
        protected QuizService $quizService,
        protected LmsService $lmsService
    ) {}

    /**
     * Buat quiz baru pada satu pertemuan (soal menyusul via attach).
     */
    public function store(StoreQuizRequest $request, int $pertemuanId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $quiz = $this->quizService->createQuiz($pertemuanId, $request->validated(), (int) $request->user()->id);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_quiz',
                recordId: $quiz->id,
                oldValues: null,
                newValues: $quiz->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log buat quiz: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz berhasil dibuat. Lampirkan soal dari bank soal untuk melengkapinya.',
            'data' => $quiz,
        ], 201);
    }

    /**
     * Perbarui metadata quiz (jendela, durasi, publish, link OBE).
     */
    public function update(UpdateQuizRequest $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $quiz = Quiz::findOrFail($quizId);
        $oldValues = $quiz->getOriginal();
        $quiz->update($request->validated());
        $newValues = $quiz->getChanges();
        $updated = $quiz->fresh(['komponenPenilaian']);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'lms_quiz',
                recordId: $quizId,
                oldValues: $oldValues,
                newValues: $newValues
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log update quiz: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Hapus quiz beserta seluruh attempt-nya.
     */
    public function destroy(int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $quiz = Quiz::findOrFail($quizId);
        $oldValues = $quiz->getOriginal();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_quiz',
                recordId: $quizId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus quiz: ' . $e->getMessage());
        }

        $this->quizService->deleteQuiz($quizId);

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Detail quiz dosen: soal lengkap beserta kunci + ringkasan attempt.
     */
    public function showManage(int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        return response()->json([
            'status' => 'success',
            'message' => 'Detail quiz berhasil diambil.',
            'data' => $this->quizService->getQuizDetailForManage($quizId),
        ]);
    }

    /**
     * Lampirkan satu soal dari bank soal master ke quiz.
     */
    public function attachSoal(AttachQuizSoalRequest $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $soal = $this->quizService->attachSoal(
            $quizId,
            (int) $request->validated('bank_soal_id'),
            $request->validated('urutan'),
            $request->validated('poin') !== null ? (float) $request->validated('poin') : null
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_quiz_soal',
                recordId: $soal->id,
                oldValues: null,
                newValues: $soal->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log lampirkan soal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Soal bank soal berhasil dilampirkan ke quiz.',
            'data' => $soal->load('bankSoal'),
        ], 201);
    }

    /**
     * Lepas soal dari quiz (ditolak bila attempt sudah ada).
     */
    public function detachSoal(int $quizSoalId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $quizSoal = QuizSoal::find($quizSoalId);
        $oldValues = $quizSoal?->getOriginal();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_quiz_soal',
                recordId: $quizSoalId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log lepas soal quiz: ' . $e->getMessage());
        }

        $this->quizService->detachSoal($quizSoalId);

        return response()->json([
            'status' => 'success',
            'message' => 'Soal berhasil dilepas dari quiz.',
            'data' => null,
        ]);
    }

    /**
     * Daftar attempt quiz (paginated) untuk dosen.
     */
    public function listAttempts(Request $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $perPage = min(100, $request->integer('per_page', 15));
        $paginator = $this->quizService->listAttempts($quizId, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar attempt quiz berhasil diambil.',
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

    /**
     * Dosen menilai manual satu jawaban (uraian/koreksi) — recompute + OBE resync.
     */
    public function beriNilaiManual(ManualQuizGradeRequest $request, int $attemptJawabanId): JsonResponse
    {
        Gate::authorize('siakad.nilai.manage');

        $attemptJawaban = QuizAttemptJawaban::findOrFail($attemptJawabanId);
        $oldValues = $attemptJawaban->getOriginal();

        $poin = (float) $request->validated('poin');
        $row = $this->quizService->beriNilaiManual(
            $attemptJawabanId,
            $poin,
            (int) $request->user()->id,
            $request->validated('feedback_dosen')
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'lms_quiz_attempt_jawaban',
                recordId: $attemptJawabanId,
                oldValues: $oldValues,
                newValues: ['poin_didapat' => $poin, 'is_benar' => $row->is_benar, 'feedback_dosen' => $row->feedback_dosen]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log nilai manual quiz: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai manual berhasil disimpan dan disinkronkan ke OBE.',
            'data' => $row,
        ]);
    }

    /**
     * Buat tryout level kelas (lintas pertemuan).
     */
    public function storeTryout(StoreQuizRequest $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $tryout = $this->quizService->createTryout($kelasId, $request->validated(), (int) $request->user()->id);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_quiz',
                recordId: $tryout->id,
                oldValues: null,
                newValues: $tryout->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log buat tryout: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tryout berhasil dibuat. Lampirkan soal dari bank soal untuk melengkapinya.',
            'data' => $tryout,
        ], 201);
    }

    /**
     * Daftar tryout satu kelas (dosen: semua; mahasiswa: published + tak diarsip + terdaftar).
     */
    public function listTryout(Request $request, int $kelasId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $forManage = $request->user()->can('siakad.kelas.manage');
        $perPage = min(100, $request->integer('per_page', 15));
        $page = max(1, $request->integer('page', 1));

        $collection = $this->quizService->listTryoutByKelas($kelasId, (int) $request->user()->id, $forManage);
        $total = $collection->count();
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar tryout kelas berhasil diambil.',
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

    /**
     * Daftar tryout dari seluruh kelas yang bisa diakses user (agregat module-level).
     *
     * Mendukung halaman /lms/tryout yang bersifat module-level (tanpa kelasId),
     * sedangkan endpoint per-kelas (/kelas/{kelasId}/tryout) tetap dipakai di
     * dalam konteks satu kelas.
     */
    public function listTryoutSaya(Request $request): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $allowedSorts = ['id', 'judul', 'durasi_menit', 'dibuka_at', 'ditutup_at', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $tahunAkademikId = $request->filled('tahun_akademik_id') ? (int) $request->input('tahun_akademik_id') : null;
        $kelasId = $request->filled('kelas_id') ? (int) $request->input('kelas_id') : null;

        $userId = (int) $request->user()->id;
        $paginator = $this->quizService->listTryoutAggregate(
            $userId,
            $this->lmsService->resolveAccessibleKelasIds($userId),
            $request->user()->can('siakad.kelas.manage'),
            $perPage,
            $search,
            $sortBy,
            $sortOrder,
            $tahunAkademikId,
            $kelasId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar tryout berhasil diambil.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters' => [
                'search'            => $search,
                'sort_by'           => $sortBy,
                'sort_order'        => $sortOrder,
                'tahun_akademik_id' => $tahunAkademikId,
                'kelas_id'          => $kelasId,
            ],
        ]);
    }

    /**
     * Tambah peserta eksplisit tryout (di luar KRS kelas).
     */
    public function addPeserta(AddTryoutPesertaRequest $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $peserta = $this->quizService->addTryoutPeserta(
            $quizId,
            (int) $request->validated('mahasiswa_id'),
            (int) $request->user()->id
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_tryout_peserta',
                recordId: $peserta->id,
                oldValues: null,
                newValues: $peserta->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tambah peserta tryout: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Peserta tryout berhasil ditambahkan.',
            'data' => $peserta->load('mahasiswa'),
        ], 201);
    }

    /**
     * Tambah peserta tryout per kelas (mis. "25A") + filter prodi opsional.
     */
    public function addPesertaByKelas(Request $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $validated = $request->validate([
            'kelas' => 'required|string|max:10|regex:/^[0-9]{2}[A-Z]{1,3}$/',
            'program_studi_id' => 'nullable|integer|exists:siakad_program_studi,id',
        ]);

        $result = $this->quizService->addTryoutPesertaByKelas(
            $quizId,
            strtoupper(trim($validated['kelas'])),
            isset($validated['program_studi_id']) ? (int) $validated['program_studi_id'] : null,
            (int) $request->user()->id
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_tryout_peserta',
                recordId: $quizId,
                oldValues: null,
                newValues: [
                    'kelas' => $result['kelas'],
                    'program_studi_id' => $validated['program_studi_id'] ?? null,
                    'added' => $result['added'],
                    'skipped' => $result['skipped'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tambah peserta tryout per kelas: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menambahkan {$result['added']} peserta tryout kelas {$result['kelas']}." . ($result['skipped'] > 0 ? " ({$result['skipped']} dilewati karena sudah terdaftar)" : ''),
            'data' => $result,
        ], 201);
    }

    /**
     * Hapus peserta eksplisit tryout.
     */
    public function removePeserta(int $pesertaId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $peserta = TryoutPeserta::find($pesertaId);
        $oldValues = $peserta?->getOriginal();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_tryout_peserta',
                recordId: $pesertaId,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus peserta tryout: ' . $e->getMessage());
        }

        $this->quizService->removeTryoutPeserta($pesertaId);

        return response()->json([
            'status' => 'success',
            'message' => 'Peserta tryout berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Detail quiz untuk mahasiswa (tanpa kunci) + attempt miliknya.
     */
    public function showMahasiswa(Request $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        return response()->json([
            'status' => 'success',
            'message' => 'Detail quiz berhasil diambil.',
            'data' => $this->quizService->getQuizDetailForMahasiswa($quizId, (int) $request->user()->id),
        ]);
    }

    /**
     * Mulai (atau lanjutkan) attempt quiz.
     */
    public function start(StartAttemptRequest $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $attempt = $this->quizService->startAttempt($quizId, (int) $request->user()->id, $request->validated('kode_akses'));

        return response()->json([
            'status' => 'success',
            'message' => 'Attempt quiz berhasil dimulai.',
            'data' => $attempt,
        ], 201);
    }

    /**
     * Ambil soal per batch TANPA kunci jawaban.
     */
    public function batchSoal(Request $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $page = max(1, $request->integer('page', 1));

        return response()->json([
            'status' => 'success',
            'message' => 'Batch soal quiz berhasil diambil.',
            'data' => $this->quizService->getBatchSoal($quizId, (int) $request->user()->id, $page),
        ]);
    }

    /**
     * Autosave jawaban (bulk). Kedaluwarsa → auto-submit.
     */
    public function autosave(AutosaveQuizRequest $request, int $attemptId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        $result = $this->quizService->autosave($attemptId, (int) $request->user()->id, $request->validated('answers'));

        return response()->json([
            'status' => 'success',
            'message' => !empty($result['auto_submitted'])
                ? 'Waktu habis — jawaban tersimpan otomatis dinilai.'
                : 'Progres jawaban berhasil disimpan.',
            'data' => $result,
        ]);
    }

    /**
     * Submit attempt: auto-grade + OBE sync (bila quiz di-link komponen OBE).
     */
    public function submit(Request $request, int $attemptId): JsonResponse
    {
        Gate::authorize('siakad.kelas.read');

        // Snapshot sebelum submit supaya audit trail memuat status & nilai attempt
        // sebelum dinilai, bukan `null`.
        $oldValues = QuizAttempt::findOrFail($attemptId)->getOriginal();

        $attempt = $this->quizService->submitAttempt($attemptId, (int) $request->user()->id);

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'update',
                tableName: 'lms_quiz_attempt',
                recordId: $attemptId,
                oldValues: $oldValues,
                newValues: ['status' => $attempt->status, 'nilai_akhir' => $attempt->nilai_akhir]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log submit quiz attempt: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz berhasil disubmit dan dinilai.',
            'data' => $attempt,
        ]);
    }

    /**
     * Daftar kolaborator dosen satu quiz (pengawas/pemantau/penginput soal).
     */
    public function listKolaborator(int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kolaborator quiz berhasil diambil.',
            'data' => $this->quizService->listKolaborator($quizId),
        ]);
    }

    /**
     * Tambah (atau perbarui peran) kolaborator dosen pada quiz.
     */
    public function addKolaborator(Request $request, int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $validated = $request->validate([
            'dosen_id' => 'required|integer|exists:siakad_dosen,id',
            'peran'    => 'required|in:' . implode(',', QuizKolaborator::PERAN),
        ]);

        $kolaborator = $this->quizService->addKolaborator(
            $quizId,
            (int) $validated['dosen_id'],
            (string) $validated['peran'],
            (int) $request->user()->id
        );

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'create',
                tableName: 'lms_quiz_kolaborator',
                recordId: $kolaborator->id,
                oldValues: null,
                newValues: $kolaborator->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tambah kolaborator quiz: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kolaborator quiz berhasil ditambahkan.',
            'data' => $kolaborator->load('dosen'),
        ], 201);
    }

    /**
     * Hapus kolaborator dosen dari quiz.
     */
    public function removeKolaborator(int $id): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        $kolaborator = QuizKolaborator::find($id);
        $oldValues = $kolaborator?->getOriginal();

        try {
            AuditLogService::record(
                module: 'LMS',
                action: 'delete',
                tableName: 'lms_quiz_kolaborator',
                recordId: $id,
                oldValues: $oldValues,
                newValues: null
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log hapus kolaborator quiz: ' . $e->getMessage());
        }

        $this->quizService->removeKolaborator($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kolaborator quiz berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Reset attempt mahasiswa agar bisa mengulang (dosen).
     */
    public function resetAttempt(Request $request, int $attemptId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        // Jejak audit dicatat atomik di dalam service (termasuk snapshot
        // jawaban yang ikut terhapus), bukan di controller.
        $this->quizService->resetAttempt($attemptId, (int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Attempt quiz berhasil direset. Mahasiswa dapat mengerjakan kembali.',
            'data' => null,
        ]);
    }

    /**
     * Detail satu attempt untuk layar grading dosen (soal + kunci + jawaban mahasiswa).
     */
    public function attemptDetail(int $attemptId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        return response()->json([
            'status' => 'success',
            'message' => 'Detail attempt quiz berhasil diambil.',
            'data' => $this->quizService->getAttemptDetailForManage($attemptId),
        ]);
    }

    /**
     * Preview quiz untuk dosen: simulasi tampilan mahasiswa tanpa kunci jawaban.
     */
    public function preview(int $quizId): JsonResponse
    {
        Gate::authorize('siakad.kelas.manage');

        return response()->json([
            'status' => 'success',
            'message' => 'Preview quiz berhasil diambil.',
            'data' => $this->quizService->getPreview($quizId),
        ]);
    }
}
