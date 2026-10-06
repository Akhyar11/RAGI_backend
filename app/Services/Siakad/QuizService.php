<?php

namespace App\Services\Siakad;

use App\Models\Lms\Quiz;
use App\Models\Lms\QuizAttempt;
use App\Models\Lms\QuizAttemptJawaban;
use App\Models\Lms\QuizKolaborator;
use App\Models\Lms\QuizSoal;
use App\Models\Lms\TryoutPeserta;
use App\Models\Siakad\BankSoal;
use App\Models\Siakad\BankSoalOpsi;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\Pertemuan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    // ---------- Helper otorisasi data ----------

    protected function resolveMahasiswa(int $userId): Mahasiswa
    {
        return Mahasiswa::where('user_id', $userId)->firstOrFail();
    }

    protected function assertStrukturFleksibel(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw ValidationException::withMessages([
                'quiz' => ['Struktur quiz sudah terkunci karena sudah ada attempt mahasiswa.'],
            ]);
        }
    }

    /**
     * Kelas efektif quiz: tryout → kelas_id langsung; kuis → via pertemuan.
     */
    protected function kelasIdOf(Quiz $quiz): ?int
    {
        if ($quiz->kelas_id) {
            return (int) $quiz->kelas_id;
        }

        return $quiz->pertemuan?->kelas_id;
    }

    protected function assertBolehMengerjakan(Quiz $quiz, int $mahasiswaId): void
    {
        $kelasId = $this->kelasIdOf($quiz);
        $viaKrs = $kelasId
            ? KrsDetail::where('kelas_id', $kelasId)
                ->whereHas('krs', function ($q) use ($mahasiswaId) {
                    $q->where('mahasiswa_id', $mahasiswaId);
                })
                ->exists()
            : false;

        $viaPeserta = TryoutPeserta::where('quiz_id', $quiz->id)
            ->where('mahasiswa_id', $mahasiswaId)
            ->exists();

        if (!$viaKrs && !$viaPeserta) {
            throw ValidationException::withMessages([
                'quiz' => ['Anda tidak terdaftar pada kelas perkuliahan ini.'],
            ]);
        }
    }

    // ---------- CRUD quiz (dosen) ----------

    public function createQuiz(int $pertemuanId, array $data, int $userId): Quiz
    {
        Pertemuan::findOrFail($pertemuanId);

        return Quiz::create([
            'pertemuan_id' => $pertemuanId,
            'kelas_id' => null,
            'tipe' => 'kuis',
            'komponen_penilaian_id' => $data['komponen_penilaian_id'] ?? null,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'durasi_menit' => $data['durasi_menit'] ?? null,
            'max_attempt' => $data['max_attempt'] ?? 1,
            'acak_soal' => $data['acak_soal'] ?? true,
            'acak_jawaban' => $data['acak_jawaban'] ?? true,
            'batch_size' => $data['batch_size'] ?? null,
            'dibuka_at' => $data['dibuka_at'] ?? null,
            'ditutup_at' => $data['ditutup_at'] ?? null,
            'is_published' => $data['is_published'] ?? false,
            'kode_akses' => null,
            'is_archived' => false,
            'dibuat_oleh' => $userId,
        ]);
    }

    /**
     * Buat tryout level kelas (lintas pertemuan), reuse seluruh mesin quiz.
     */
    public function createTryout(int $kelasId, array $data, int $userId): Quiz
    {
        \App\Models\Siakad\Kelas::findOrFail($kelasId);

        return Quiz::create([
            'pertemuan_id' => null,
            'kelas_id' => $kelasId,
            'tipe' => 'tryout',
            'komponen_penilaian_id' => $data['komponen_penilaian_id'] ?? null,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'durasi_menit' => $data['durasi_menit'] ?? null,
            'max_attempt' => $data['max_attempt'] ?? 1,
            'acak_soal' => $data['acak_soal'] ?? true,
            'acak_jawaban' => $data['acak_jawaban'] ?? true,
            'batch_size' => $data['batch_size'] ?? null,
            'dibuka_at' => $data['dibuka_at'] ?? null,
            'ditutup_at' => $data['ditutup_at'] ?? null,
            'is_published' => $data['is_published'] ?? false,
            'kode_akses' => $data['kode_akses'] ?? null,
            'is_archived' => false,
            'dibuat_oleh' => $userId,
        ]);
    }

    /**
     * Daftar tryout satu kelas. Manage: semua; mahasiswa: published + tak diarsip + terdaftar.
     */
    public function listTryoutByKelas(int $kelasId, int $userId, bool $forManage): \Illuminate\Support\Collection
    {
        $query = Quiz::where('kelas_id', $kelasId)->where('tipe', 'tryout')
            ->with(['komponenPenilaian'])
            ->withCount('soal')
            ->orderByDesc('id');

        if (!$forManage) {
            $query->where('is_published', true)->where('is_archived', false);
        }

        $list = $query->get();

        if ($forManage) {
            return $list;
        }

        $mahasiswa = $this->resolveMahasiswa($userId);

        return $list->filter(function (Quiz $quiz) use ($mahasiswa) {
            $kelasId = (int) $quiz->kelas_id;
            $viaKrs = KrsDetail::where('kelas_id', $kelasId)
                ->whereHas('krs', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
                ->exists();
            if ($viaKrs) {
                return true;
            }

            return TryoutPeserta::where('quiz_id', $quiz->id)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->exists();
        })->values();
    }

    /**
     * Daftar tryout dari SELURUH kelas yang bisa diakses user (agregat module-level).
     *
     * Berbeda dengan listTryoutByKelas() yang dipanggil per kelas, method ini
     * melakukan pagination di level database dan tidak memanggil resolveMahasiswa()
     * sehingga aman untuk user yang bukan mahasiswa (dosen/kaprodi/admin).
     *
     * @param  array<int>|null  $kelasIds  null = semua kelas (privileged)
     */
    public function listTryoutAggregate(
        int $userId,
        ?array $kelasIds,
        bool $forManage,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortOrder = 'desc',
        ?int $tahunAkademikId = null,
        ?int $kelasId = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Quiz::where('tipe', 'tryout')
            ->with(['komponenPenilaian', 'kelas.mataKuliah', 'kelas.tahunAkademik'])
            ->withCount('soal');

        if ($kelasIds !== null) {
            $query->whereIn('kelas_id', $kelasIds ?: [0]);
        }

        // Filter kelas digabung (AND) dengan whereIn di atas sehingga kelas di luar
        // hak user tidak bisa dimunculkan hanya lewat kelas_id.
        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        if ($tahunAkademikId) {
            $query->whereHas('kelas', function ($q) use ($tahunAkademikId) {
                $q->where('tahun_akademik_id', $tahunAkademikId);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%")
                  ->orWhereHas('kelas', function ($kelas) use ($search) {
                      $kelas->where('nama_kelas', 'like', "%{$search}%")
                            ->orWhere('kode_kelas', 'like', "%{$search}%")
                            ->orWhereHas('mataKuliah', function ($mk) use ($search) {
                                $mk->where('nama', 'like', "%{$search}%")
                                   ->orWhere('kode_mk', 'like', "%{$search}%");
                            });
                  });
            });
        }

        if (!$forManage) {
            // Mahasiswa hanya melihat tryout yang published, tidak diarsipkan,
            // dan sesuai dengan aksesnya lewat KRS kelas atau peserta eksplisit.
            $query->where('is_published', true)->where('is_archived', false);

            $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
            if (!$mahasiswa) {
                return Quiz::whereRaw('1 = 0')->paginate($perPage);
            }

            $query->where(function ($q) use ($mahasiswa) {
                $q->whereHas('kelas.krsDetails', function ($kd) use ($mahasiswa) {
                    $kd->whereHas('krs', function ($k) use ($mahasiswa) {
                        $k->where('mahasiswa_id', $mahasiswa->id);
                    });
                })
                ->orWhereHas('tryoutPeserta', function ($tp) use ($mahasiswa) {
                    $tp->where('mahasiswa_id', $mahasiswa->id);
                });
            });
        }

        $allowedSorts = ['id', 'judul', 'durasi_menit', 'dibuka_at', 'ditutup_at', 'created_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function addTryoutPeserta(int $quizId, int $mahasiswaId, int $olehUserId): TryoutPeserta
    {
        $quiz = Quiz::findOrFail($quizId);

        if ($quiz->tipe !== 'tryout') {
            throw ValidationException::withMessages([
                'quiz' => ['Peserta eksplisit hanya berlaku untuk tryout.'],
            ]);
        }

        return TryoutPeserta::firstOrCreate(
            ['quiz_id' => $quizId, 'mahasiswa_id' => $mahasiswaId],
            ['ditambah_oleh' => $olehUserId]
        );
    }

    /**
     * Tambah peserta tryout per kelas (mis. "25A"): seluruh mahasiswa aktif
     * dengan kolom siakad_mahasiswa.kelas yang cocok + filter prodi opsional.
     * Idempoten per mahasiswa (firstOrCreate).
     *
     * @return array{added: int, skipped: int, kelas: string}
     */
    public function addTryoutPesertaByKelas(int $quizId, string $kelas, ?int $programStudiId, int $olehUserId): array
    {
        return DB::transaction(function () use ($quizId, $kelas, $programStudiId, $olehUserId) {
            $quiz = Quiz::findOrFail($quizId);

            if ($quiz->tipe !== 'tryout') {
                throw ValidationException::withMessages([
                    'quiz' => ['Peserta eksplisit hanya berlaku untuk tryout.'],
                ]);
            }

            $kelas = strtoupper(trim($kelas));

            $mahasiswaIds = Mahasiswa::where('status', 'aktif')
                ->where('kelas', $kelas)
                ->when($programStudiId, fn ($q) => $q->where('program_studi_id', $programStudiId))
                ->pluck('id');

            $added = 0;
            $skipped = 0;

            foreach ($mahasiswaIds as $mahasiswaId) {
                $peserta = TryoutPeserta::firstOrCreate(
                    ['quiz_id' => $quizId, 'mahasiswa_id' => $mahasiswaId],
                    ['ditambah_oleh' => $olehUserId]
                );

                if ($peserta->wasRecentlyCreated) {
                    $added++;
                } else {
                    $skipped++;
                }
            }

            return ['added' => $added, 'skipped' => $skipped, 'kelas' => $kelas];
        });
    }

    public function removeTryoutPeserta(int $pesertaId): bool
    {
        return (bool) TryoutPeserta::findOrFail($pesertaId)->delete();
    }

    public function updateQuiz(int $quizId, array $data): Quiz
    {
        $quiz = Quiz::findOrFail($quizId);
        $quiz->update($data);

        return $quiz->fresh(['komponenPenilaian']);
    }

    public function deleteQuiz(int $quizId): bool
    {
        return DB::transaction(function () use ($quizId) {
            $quiz = Quiz::findOrFail($quizId);

            return $quiz->delete();
        });
    }

    /**
     * Lampirkan soal dari bank soal master ke quiz.
     */
    public function attachSoal(int $quizId, int $bankSoalId, ?int $urutan = null, ?float $poin = null): QuizSoal
    {
        return DB::transaction(function () use ($quizId, $bankSoalId, $urutan, $poin) {
            $quiz = Quiz::findOrFail($quizId);
            $this->assertStrukturFleksibel($quiz);

            $bankSoal = BankSoal::findOrFail($bankSoalId);

            if ($quiz->soal()->where('bank_soal_id', $bankSoalId)->exists()) {
                throw ValidationException::withMessages([
                    'bank_soal_id' => ['Soal ini sudah ada di dalam quiz.'],
                ]);
            }

            return QuizSoal::create([
                'quiz_id' => $quizId,
                'bank_soal_id' => $bankSoalId,
                'urutan' => $urutan ?? ((QuizSoal::where('quiz_id', $quizId)->max('urutan') ?? 0) + 1),
                'poin' => $poin ?? 1,
            ]);
        });
    }

    public function detachSoal(int $quizSoalId): bool
    {
        return DB::transaction(function () use ($quizSoalId) {
            $quizSoal = QuizSoal::with('quiz')->findOrFail($quizSoalId);
            $this->assertStrukturFleksibel($quizSoal->quiz);

            return $quizSoal->delete();
        });
    }

    /**
     * Detail quiz untuk dosen: soal lengkap BESERTA kunci (otorisasi manage).
     */
    public function getQuizDetailForManage(int $quizId): array
    {
        $quiz = Quiz::with([
            'pertemuan.kelas.mataKuliah',
            'kelas.mataKuliah',
            'komponenPenilaian',
            'soal.bankSoal.opsi',
            'soal.bankSoal.subCpmk',
            'tryoutPeserta.mahasiswa',
        ])->findOrFail($quizId);

        $attempts = QuizAttempt::where('quiz_id', $quizId)
            ->selectRaw('status, COUNT(*) as total, AVG(nilai_akhir) as rata_nilai')
            ->groupBy('status')
            ->get();

        return [
            'quiz' => $quiz,
            'total_soal' => $quiz->soal->count(),
            'total_poin' => (float) $quiz->soal->sum('poin'),
            'attempt_summary' => $attempts,
            'total_attempt' => QuizAttempt::where('quiz_id', $quizId)->count(),
        ];
    }

    /**
     * Detail quiz untuk mahasiswa: tanpa kunci jawaban + attempt miliknya.
     */
    public function getQuizDetailForMahasiswa(int $quizId, int $userId): array
    {
        $mahasiswa = $this->resolveMahasiswa($userId);
        $quiz = Quiz::with(['pertemuan.kelas', 'kelas', 'soal'])->findOrFail($quizId);

        if (!$quiz->is_published) {
            throw ValidationException::withMessages([
                'quiz' => ['Quiz belum dipublikasikan dosen.'],
            ]);
        }
        if ($quiz->is_archived) {
            throw ValidationException::withMessages([
                'quiz' => ['Tryout sudah diarsipkan dan tidak dapat dikerjakan.'],
            ]);
        }
        $this->assertBolehMengerjakan($quiz, $mahasiswa->id);

        $myAttempts = QuizAttempt::where('quiz_id', $quizId)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->with('jawaban')
            ->orderBy('attempt_ke')
            ->get();

        return [
            // Kode akses TIDAK boleh bocor ke mahasiswa.
            'quiz' => $quiz->makeHidden(['kode_akses']),
            'total_soal' => $quiz->soal->count(),
            'total_poin' => (float) $quiz->soal->sum('poin'),
            'dalam_jendela' => $quiz->isDalamJendela(),
            'sisa_attempt' => max(0, $quiz->max_attempt - $myAttempts->where('status', 'selesai')->count()),
            'my_attempts' => $myAttempts,
            'active_attempt' => $myAttempts->firstWhere('status', 'berlangsung'),
        ];
    }

    public function listAttempts(int $quizId, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Quiz::findOrFail($quizId);

        return QuizAttempt::where('quiz_id', $quizId)
            ->with(['mahasiswa', 'jawaban'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Reset attempt mahasiswa agar bisa mengulang (dosen).
     * Hapus jawaban dulu lalu attempt dalam satu transaksi; jejak audit
     * dicatat atomik di dalam transaksi yang sama (bukan di controller).
     */
    public function resetAttempt(int $attemptId, ?int $olehUserId = null): void
    {
        DB::transaction(function () use ($attemptId, $olehUserId) {
            $attempt = QuizAttempt::with('jawaban')->findOrFail($attemptId);

            $oldValues = [
                'attempt' => $attempt->getOriginal(),
                'jawaban_count' => $attempt->jawaban->count(),
                'jawaban' => $attempt->jawaban->map->getOriginal()->all(),
            ];
            $attemptIdSnapshot = $attempt->id;

            QuizAttemptJawaban::where('attempt_id', $attempt->id)->delete();
            $attempt->delete();

            try {
                \App\Services\AuditLogService::record(
                    module: 'LMS',
                    action: 'delete',
                    tableName: 'lms_quiz_attempt',
                    recordId: $attemptIdSnapshot,
                    oldValues: $oldValues,
                    newValues: null
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log reset quiz attempt: ' . $e->getMessage());
            }
        });
    }

    /**
     * Detail satu attempt untuk layar grading dosen:
     * attempt + mahasiswa (nim, nama) + quiz (judul) + per-soal beserta
     * kunci opsi dan jawaban mahasiswa.
     */
    public function getAttemptDetailForManage(int $attemptId): array
    {
        $attempt = QuizAttempt::with([
            'mahasiswa',
            'quiz.soal.bankSoal.opsi',
            'jawaban',
        ])->findOrFail($attemptId);

        $bySoal = $attempt->jawaban->keyBy('quiz_soal_id');

        $questions = $attempt->quiz->soal->map(function (QuizSoal $qs) use ($bySoal) {
            $bank = $qs->bankSoal;
            $row = $bySoal->get($qs->id);

            return [
                'quiz_soal_id' => $qs->id,
                'urutan' => $qs->urutan,
                'poin' => (float) $qs->poin,
                'tipe_soal' => $bank?->tipe_soal,
                'pertanyaan' => $bank?->pertanyaan,
                'gambar_path' => $bank?->gambar_path,
                'opsi' => $bank?->opsi->map(fn ($o) => [
                    'id' => $o->id,
                    'teks' => $o->teks,
                    'gambar_path' => $o->gambar_path,
                    'urutan' => $o->urutan,
                    'is_benar' => (bool) $o->is_benar,
                ])->values()->all() ?? [],
                'jawaban' => $row ? [
                    'bank_opsi_id' => $row->bank_opsi_id,
                    'jawaban_teks' => $row->jawaban_teks,
                    'is_benar' => $row->is_benar,
                    'poin_didapat' => $row->poin_didapat !== null ? (float) $row->poin_didapat : null,
                    'feedback_dosen' => $row->feedback_dosen,
                ] : null,
            ];
        })->values()->all();

        return [
            'attempt' => $attempt->only([
                'id', 'quiz_id', 'mahasiswa_id', 'attempt_ke', 'status',
                'dimulai_at', 'disubmit_at', 'nilai_akhir',
                'butuh_penilaian_manual', 'dinilai_oleh', 'dinilai_at',
            ]),
            'mahasiswa' => [
                'id' => $attempt->mahasiswa?->id,
                'nim' => $attempt->mahasiswa?->nim,
                'nama' => $attempt->mahasiswa?->nama_lengkap,
            ],
            'quiz' => [
                'id' => $attempt->quiz?->id,
                'judul' => $attempt->quiz?->judul,
                'tipe' => $attempt->quiz?->tipe,
                'durasi_menit' => $attempt->quiz?->durasi_menit,
                'max_attempt' => $attempt->quiz?->max_attempt,
            ],
            'questions' => $questions,
        ];
    }

    /**
     * Preview quiz untuk dosen: meta quiz + soal + opsi TANPA kunci
     * (tanpa is_benar / kunci_jawaban / pembahasan). Simulasi tampilan
     * mahasiswa; dosen tidak mengerjakan, hanya melihat.
     */
    public function getPreview(int $quizId): array
    {
        $quiz = Quiz::with(['soal.bankSoal.opsi'])->findOrFail($quizId);

        $soal = $quiz->soal->map(function (QuizSoal $qs) {
            $bank = $qs->bankSoal;

            return [
                'quiz_soal_id' => $qs->id,
                'urutan' => $qs->urutan,
                'poin' => (float) $qs->poin,
                'tipe_soal' => $bank?->tipe_soal,
                'pertanyaan' => $bank?->pertanyaan,
                'gambar_path' => $bank?->gambar_path,
                // SENGAJA tanpa is_benar / kunci_jawaban / pembahasan.
                'opsi' => $bank?->opsi->map(fn ($o) => [
                    'id' => $o->id,
                    'teks' => $o->teks,
                    'gambar_path' => $o->gambar_path,
                    'urutan' => $o->urutan,
                ])->values()->all() ?? [],
            ];
        })->values()->all();

        return [
            // Meta quiz SENGAJA tanpa relasi soal agar kunci (is_benar /
            // kunci_jawaban / pembahasan) tidak bocor lewat nested relation.
            'quiz' => [
                'id' => $quiz->id,
                'pertemuan_id' => $quiz->pertemuan_id,
                'kelas_id' => $quiz->kelas_id,
                'tipe' => $quiz->tipe,
                'komponen_penilaian_id' => $quiz->komponen_penilaian_id,
                'judul' => $quiz->judul,
                'deskripsi' => $quiz->deskripsi,
                'durasi_menit' => $quiz->durasi_menit,
                'max_attempt' => $quiz->max_attempt,
                'acak_soal' => $quiz->acak_soal,
                'acak_jawaban' => $quiz->acak_jawaban,
                'batch_size' => $quiz->batch_size,
                'dibuka_at' => $quiz->dibuka_at,
                'ditutup_at' => $quiz->ditutup_at,
                'is_published' => $quiz->is_published,
                'kode_akses' => $quiz->kode_akses,
                'is_archived' => $quiz->is_archived,
                'dibuat_oleh' => $quiz->dibuat_oleh,
            ],
            'total_soal' => count($soal),
            'total_poin' => (float) $quiz->soal->sum('poin'),
            'data' => $soal,
        ];
    }

    // ---------- Alur pengerjaan (mahasiswa) ----------

    public function startAttempt(int $quizId, int $userId, ?string $kodeAkses = null): QuizAttempt
    {
        return DB::transaction(function () use ($quizId, $userId, $kodeAkses) {
            $mahasiswa = $this->resolveMahasiswa($userId);
            $quiz = Quiz::with(['pertemuan', 'soal'])->findOrFail($quizId);

            if (!$quiz->is_published) {
                throw ValidationException::withMessages([
                    'quiz' => ['Quiz belum dipublikasikan dosen.'],
                ]);
            }
            if ($quiz->is_archived) {
                throw ValidationException::withMessages([
                    'quiz' => ['Tryout sudah diarsipkan dan tidak dapat dikerjakan.'],
                ]);
            }
            if (!$quiz->isDalamJendela()) {
                throw ValidationException::withMessages([
                    'quiz' => ['Quiz sedang tidak dalam jendela pengerjaan.'],
                ]);
            }
            if ($quiz->kode_akses && !hash_equals((string) $quiz->kode_akses, trim((string) $kodeAkses))) {
                throw ValidationException::withMessages([
                    'kode_akses' => ['Kode akses tryout tidak cocok.'],
                ]);
            }
            if ($quiz->soal->isEmpty()) {
                throw ValidationException::withMessages([
                    'quiz' => ['Quiz belum memiliki soal.'],
                ]);
            }
            $this->assertBolehMengerjakan($quiz, $mahasiswa->id);

            // Lanjutkan attempt yang masih berjalan bila ada.
            $berlangsung = QuizAttempt::where('quiz_id', $quizId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('status', 'berlangsung')
                ->first();
            if ($berlangsung) {
                return $berlangsung;
            }

            $selesaiCount = QuizAttempt::where('quiz_id', $quizId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('status', 'selesai')
                ->count();
            if ($selesaiCount >= $quiz->max_attempt) {
                throw ValidationException::withMessages([
                    'quiz' => ['Batas percobaan quiz Anda sudah habis.'],
                ]);
            }

            return QuizAttempt::create([
                'quiz_id' => $quizId,
                'mahasiswa_id' => $mahasiswa->id,
                'attempt_ke' => $selesaiCount + 1,
                'status' => 'berlangsung',
                'dimulai_at' => now(),
            ]);
        });
    }

    protected function resolveActiveAttempt(int $attemptId, int $userId): QuizAttempt
    {
        $mahasiswa = $this->resolveMahasiswa($userId);

        $attempt = QuizAttempt::with('quiz.pertemuan')->findOrFail($attemptId);

        if ((int) $attempt->mahasiswa_id !== (int) $mahasiswa->id) {
            throw ValidationException::withMessages([
                'attempt' => ['Attempt ini bukan milik Anda.'],
            ]);
        }
        if (!$attempt->isBerlangsung()) {
            throw ValidationException::withMessages([
                'attempt' => ['Attempt sudah selesai dan tidak dapat diubah.'],
            ]);
        }

        return $attempt;
    }

    protected function isAttemptKedaluwarsa(QuizAttempt $attempt): bool
    {
        $durasi = $attempt->quiz->durasi_menit;

        if (!$durasi || $durasi <= 0) {
            return false;
        }

        return now()->gt($attempt->dimulai_at->copy()->addMinutes($durasi));
    }

    /**
     * Ambil soal per batch TANPA kunci jawaban (anti contekan).
     * Urutan acak deterministik per attempt agar stabil antar halaman batch.
     */
    public function getBatchSoal(int $quizId, int $userId, int $page = 1): array
    {
        $attempt = QuizAttempt::where('quiz_id', $quizId)
            ->where('mahasiswa_id', $this->resolveMahasiswa($userId)->id)
            ->where('status', 'berlangsung')
            ->firstOrFail();

        $quiz = Quiz::with(['soal.bankSoal.opsi'])->findOrFail($quizId);

        if ($this->isAttemptKedaluwarsa($attempt)) {
            $this->finalizeAttempt($attempt->fresh(['quiz.soal.bankSoal.opsi']));

            throw ValidationException::withMessages([
                'attempt' => ['Waktu pengerjaan habis. Jawaban tersimpan otomatis dinilai.'],
            ]);
        }

        $batchSize = $quiz->effectiveBatchSize();
        $page = max(1, $page);

        $all = $quiz->soal;
        if ($quiz->acak_soal) {
            $seed = (string) $attempt->id;
            $all = $all->sortBy(fn ($s) => md5($seed . '-' . $s->id))->values();
        }

        $total = $all->count();
        $items = $all->slice(($page - 1) * $batchSize, $batchSize)->values();

        $data = $items->map(function (QuizSoal $qs) use ($quiz, $attempt) {
            $bank = $qs->bankSoal;
            $opsi = $bank->opsi;
            if ($quiz->acak_jawaban) {
                $opsi = $opsi->sortBy(fn ($o) => md5($attempt->id . '-' . $o->id))->values();
            }

            return [
                'quiz_soal_id' => $qs->id,
                'urutan' => $qs->urutan,
                'poin' => (float) $qs->poin,
                'tipe_soal' => $bank->tipe_soal,
                'pertanyaan' => $bank->pertanyaan,
                'gambar_path' => $bank->gambar_path,
                // SENGAJA tanpa is_benar / kunci_jawaban / pembahasan.
                'opsi' => $opsi->map(fn ($o) => [
                    'id' => $o->id,
                    'teks' => $o->teks,
                    'gambar_path' => $o->gambar_path,
                    'urutan' => $o->urutan,
                ])->values(),
            ];
        })->values();

        return [
            'attempt_id' => $attempt->id,
            'page' => $page,
            'batch_size' => $batchSize,
            'total_soal' => $total,
            'total_pages' => (int) ceil($total / $batchSize),
            'data' => $data,
        ];
    }

    /**
     * Autosave jawaban (bulk upsert). Kedaluwarsa → finalisasi otomatis.
     *
     * @param array $answers [{quiz_soal_id, bank_opsi_id?, jawaban_teks?}]
     */
    public function autosave(int $attemptId, int $userId, array $answers): array
    {
        return DB::transaction(function () use ($attemptId, $userId, $answers) {
            $attempt = $this->resolveActiveAttempt($attemptId, $userId);
            $attempt->load('quiz.soal');

            if ($this->isAttemptKedaluwarsa($attempt)) {
                $this->finalizeAttempt($attempt);

                return ['auto_submitted' => true, 'attempt_id' => $attempt->id];
            }

            $quizSoalIds = $attempt->quiz->soal->pluck('id')->all();
            $opsiIds = collect($answers)->pluck('bank_opsi_id')->filter()->unique()->values()->all();

            // Validasi batch: soal milik quiz ini (N+1 → 1 query).
            $inputSoalIds = collect($answers)->pluck('quiz_soal_id')->unique()->values()->all();
            $invalidSoal = array_diff($inputSoalIds, $quizSoalIds);
            if (!empty($invalidSoal)) {
                throw ValidationException::withMessages([
                    'answers' => ['Beberapa soal tidak termasuk dalam quiz ini.'],
                ]);
            }

            // Validasi batch: opsi milik bank soal dari soal terkait.
            if (!empty($opsiIds)) {
                $allowedOpsi = BankSoalOpsi::whereIn('id', $opsiIds)
                    ->whereIn('bank_soal_id', $attempt->quiz->soal->pluck('bank_soal_id'))
                    ->pluck('id')->all();
                if (count($allowedOpsi) !== count($opsiIds)) {
                    throw ValidationException::withMessages([
                        'answers' => ['Beberapa opsi jawaban tidak valid untuk quiz ini.'],
                    ]);
                }
            }

            $existing = QuizAttemptJawaban::where('attempt_id', $attempt->id)
                ->whereIn('quiz_soal_id', $inputSoalIds)
                ->get()->keyBy('quiz_soal_id');

            foreach ($answers as $ans) {
                $row = $existing->get($ans['quiz_soal_id']);
                $payload = [
                    'bank_opsi_id' => $ans['bank_opsi_id'] ?? null,
                    'jawaban_teks' => $ans['jawaban_teks'] ?? null,
                ];
                if ($row) {
                    if ($row->bank_opsi_id != $payload['bank_opsi_id'] || $row->jawaban_teks != $payload['jawaban_teks']) {
                        $row->update($payload);
                    }
                } else {
                    QuizAttemptJawaban::create([
                        'attempt_id' => $attempt->id,
                        'quiz_soal_id' => $ans['quiz_soal_id'],
                    ] + $payload);
                }
            }

            return ['auto_submitted' => false, 'attempt_id' => $attempt->id, 'saved' => count($answers)];
        });
    }

    public function submitAttempt(int $attemptId, int $userId): QuizAttempt
    {
        return DB::transaction(function () use ($attemptId, $userId) {
            $attempt = $this->resolveActiveAttempt($attemptId, $userId);
            $attempt->load('quiz.soal.bankSoal.opsi');

            return $this->finalizeAttempt($attempt);
        });
    }

    /**
     * Nilai + finalisasi attempt: auto-grade PG/isian, uraian menunggu manual.
     */
    protected function finalizeAttempt(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->loadMissing(['quiz.soal.bankSoal.opsi', 'jawaban']);

        $bySoal = $attempt->jawaban->keyBy('quiz_soal_id');
        $butuhManual = false;

        foreach ($attempt->quiz->soal as $qs) {
            /** @var QuizSoal $qs */
            $bank = $qs->bankSoal;
            $row = $bySoal->get($qs->id);

            if (!$row) {
                $row = QuizAttemptJawaban::create([
                    'attempt_id' => $attempt->id,
                    'quiz_soal_id' => $qs->id,
                    'is_benar' => false,
                    'poin_didapat' => 0,
                ]);
                $bySoal->put($qs->id, $row);
                continue;
            }

            if ($bank->tipe_soal === 'pilihan_ganda') {
                $benar = $row->bank_opsi_id
                    ? (bool) $bank->opsi->firstWhere('id', $row->bank_opsi_id)?->is_benar
                    : false;
                $row->update([
                    'is_benar' => $benar,
                    'poin_didapat' => $benar ? $qs->poin : 0,
                ]);
            } elseif ($bank->tipe_soal === 'isian_singkat') {
                $benar = $this->cocokIsian($row->jawaban_teks, $bank->kunci_jawaban);
                $row->update([
                    'is_benar' => $benar,
                    'poin_didapat' => $benar ? $qs->poin : 0,
                ]);
            } else {
                // Uraian: menunggu penilaian dosen.
                $butuhManual = true;
            }
        }

        $attempt->update([
            'status' => 'selesai',
            'disubmit_at' => now(),
            'nilai_akhir' => $this->hitungNilai($attempt->fresh('jawaban'), $attempt->quiz->soal),
            'butuh_penilaian_manual' => $butuhManual,
        ]);

        $this->syncNilaiKeObe($attempt->fresh(['quiz', 'jawaban']));

        return $attempt->fresh(['jawaban.quizSoal.bankSoal', 'quiz']);
    }

    protected function cocokIsian(?string $jawaban, ?string $kunci): bool
    {
        if ($jawaban === null || $kunci === null) {
            return false;
        }

        $normal = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $s)));

        return $normal($jawaban) !== '' && $normal($jawaban) === $normal($kunci);
    }

    protected function hitungNilai(QuizAttempt $attempt, $quizSoal): float
    {
        $total = (float) $quizSoal->sum('poin');
        if ($total <= 0) {
            return 0;
        }

        $didapat = (float) $attempt->jawaban->sum('poin_didapat');

        return round(($didapat / $total) * 100, 2);
    }

    /**
     * Dosen menilai manual satu jawaban (uraian / koreksi), lalu recompute + OBE resync.
     */
    public function beriNilaiManual(int $attemptJawabanId, float $poin, int $dosenUserId, ?string $feedback = null): QuizAttemptJawaban
    {
        return DB::transaction(function () use ($attemptJawabanId, $poin, $dosenUserId, $feedback) {
            $row = QuizAttemptJawaban::with(['attempt.quiz.soal', 'quizSoal.bankSoal'])->findOrFail($attemptJawabanId);
            $batas = (float) $row->quizSoal->poin;

            if ($poin < 0 || $poin > $batas) {
                throw ValidationException::withMessages([
                    'poin' => ["Poin harus berada di antara 0 dan {$batas}."],
                ]);
            }

            $row->update([
                'poin_didapat' => $poin,
                'is_benar' => $poin >= $batas,
                'feedback_dosen' => $feedback,
            ]);

            $attempt = $row->attempt;
            $attempt->load(['quiz.soal', 'jawaban']);
            $masihPending = $attempt->jawaban->contains(fn ($j) => $j->is_benar === null);

            $attempt->update([
                'nilai_akhir' => $this->hitungNilai($attempt, $attempt->quiz->soal),
                'butuh_penilaian_manual' => $masihPending,
                'dinilai_oleh' => $dosenUserId,
                'dinilai_at' => now(),
            ]);

            $this->syncNilaiKeObe($attempt->fresh(['quiz']));

            return $row->fresh(['attempt', 'quizSoal.bankSoal']);
        });
    }

    /**
     * Upsert nilai akhir quiz ke OBE (pola sama dengan nilai tugas).
     */    protected function syncNilaiKeObe(QuizAttempt $attempt): void
    {
        $quiz = $attempt->quiz;

        if (!$quiz->komponen_penilaian_id) {
            return;
        }

        $kelasId = $quiz->kelas_id;
        if (!$kelasId && $quiz->pertemuan_id) {
            $quiz->loadMissing('pertemuan');
            $kelasId = $quiz->pertemuan?->kelas_id;
        }
        if (!$kelasId) {
            return;
        }

        if ($attempt->nilai_akhir === null) {
            return;
        }

        $mahasiswaId = $attempt->mahasiswa_id;

        $krsDetail = KrsDetail::where('kelas_id', $kelasId)
            ->whereHas('krs', function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId);
            })
            ->first();

        if (!$krsDetail) {
            return;
        }

        NilaiKomponenMahasiswa::updateOrCreate(
            [
                'krs_detail_id' => $krsDetail->id,
                'komponen_penilaian_id' => $quiz->komponen_penilaian_id,
            ],
            [
                'nilai_angka' => $attempt->nilai_akhir,
                'catatan_feedback' => 'Nilai quiz: ' . $quiz->judul,
                'diinput_oleh' => $attempt->dinilai_oleh,
            ]
        );
    }

    // ---------- Kolaborator quiz (dosen pengawas/pemantau/penginput soal) ----------

    /**
     * Daftar kolaborator satu quiz beserta data dosennya.
     */
    public function listKolaborator(int $quizId): \Illuminate\Support\Collection
    {
        Quiz::findOrFail($quizId);

        return QuizKolaborator::where('quiz_id', $quizId)
            ->with(['dosen'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Tambah (atau perbarui peran) kolaborator dosen pada quiz.
     * Idempoten per pasangan quiz-dosen; peran divalidasi closed-set.
     */
    public function addKolaborator(int $quizId, int $dosenId, string $peran, ?int $olehUserId): QuizKolaborator
    {
        Quiz::findOrFail($quizId);
        Dosen::findOrFail($dosenId);

        if (!in_array($peran, QuizKolaborator::PERAN, true)) {
            throw ValidationException::withMessages([
                'peran' => ['Peran kolaborator tidak valid. Pilihan: ' . implode(', ', QuizKolaborator::PERAN) . '.'],
            ]);
        }

        return QuizKolaborator::updateOrCreate(
            ['quiz_id' => $quizId, 'dosen_id' => $dosenId],
            ['peran' => $peran, 'ditambah_oleh' => $olehUserId]
        );
    }

    public function removeKolaborator(int $kolaboratorId): bool
    {
        return (bool) QuizKolaborator::findOrFail($kolaboratorId)->delete();
    }
}
