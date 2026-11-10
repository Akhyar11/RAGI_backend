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

        $quiz->loadMissing('pertemuan');

        return $quiz->pertemuan?->kelas_id ? (int) $quiz->pertemuan->kelas_id : null;
    }

    public function assertCanManageQuiz(Quiz $quiz, int $userId): void
    {
        $dosen = Dosen::where('user_id', $userId)->first();
        if ($dosen) {
            $isKolaborator = QuizKolaborator::where('quiz_id', $quiz->id)
                ->where('dosen_id', $dosen->id)
                ->exists();
            if ($isKolaborator) {
                return;
            }
        }

        $kelasId = $this->kelasIdOf($quiz);
        if ($kelasId && app(LmsService::class)->isDosenPengampuKelas($userId, $kelasId)) {
            return;
        }

        // Tryout standalone (di luar mata kuliah / ujian khusus): pembuat, kolaborator dosen, atau pengelola berhak mengelola
        if (!$kelasId && $quiz->tipe === 'tryout') {
            if ((int) $quiz->dibuat_oleh === $userId) {
                return;
            }
            $user = \App\Models\User::find($userId);
            $lmsService = app(LmsService::class);
            if ($lmsService->isGlobalLmsManager($user, $dosen) || ($dosen && $lmsService->resolveManagedProdiIds($user, $dosen) !== [])) {
                return;
            }
        }

        abort(403, 'Aksi kelola quiz/tryout ini hanya dapat dilakukan oleh Dosen Pengampu atau Kolaborator yang ditugaskan.');
    }

    public function assertCanInspectQuiz(Quiz $quiz, int $userId): void
    {
        $dosen = Dosen::where('user_id', $userId)->first();
        if ($dosen && (
            QuizKolaborator::where('quiz_id', $quiz->id)->where('dosen_id', $dosen->id)->exists()
            || (!$this->kelasIdOf($quiz) && (int) $quiz->dibuat_oleh === $userId)
        )) {
            return;
        }

        $kelasId = $this->kelasIdOf($quiz);
        if ($kelasId) {
            $context = app(LmsService::class)->resolveKelasViewerContext($kelasId, $userId);
            if ($context['is_pengampu'] || $context['is_pengelola']) {
                return;
            }
        } else {
            $user = \App\Models\User::find($userId);
            $lmsService = app(LmsService::class);
            if ($lmsService->isGlobalLmsManager($user, $dosen) || ($dosen && $lmsService->resolveManagedProdiIds($user, $dosen) !== [])) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki akses untuk memantau detail quiz/tryout ini.');
    }

    protected function assertBolehMengerjakan(Quiz $quiz, int $mahasiswaId): void
    {
        $kelasId = $this->kelasIdOf($quiz);
        $viaKrs = $kelasId
            ? app(LmsService::class)->isMahasiswaEnrolledInKelas($mahasiswaId, $kelasId)
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
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        app(LmsService::class)->assertDosenPengampuKelas((int) $pertemuan->kelas_id, $userId);

        return Quiz::create([
            'pertemuan_id' => $pertemuanId,
            'kelas_id' => null,
            'tipe' => 'kuis',
            'komponen_penilaian_id' => $data['komponen_penilaian_id'] ?? null,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'durasi_menit' => $data['durasi_menit'] ?? null,
            'max_attempt' => $data['max_attempt'] ?? 1,
            'passing_score' => $data['passing_score'] ?? null,
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
     * Buat tryout level kelas maupun ujian khusus lintas mata kuliah ($kelasId = null),
     * reuse seluruh mesin quiz + registrasi tim dosen kolaborator.
     */
    public function createTryout(?int $kelasId, array $data, int $userId): Quiz
    {
        $dosenPembuat = Dosen::where('user_id', $userId)->first();

        if ($kelasId !== null) {
            \App\Models\Siakad\Kelas::findOrFail($kelasId);
            app(LmsService::class)->assertDosenPengampuKelas($kelasId, $userId);
        } else {
            $user = \App\Models\User::find($userId);
            $lmsService = app(LmsService::class);
            $canCreateStandalone = $dosenPembuat !== null
                || $lmsService->isGlobalLmsManager($user, $dosenPembuat)
                || ($user && $user->hasPermission('siakad.kelas.manage'));
            if (!$canCreateStandalone) {
                abort(403, 'Pembuatan tryout ujian khusus hanya dapat dilakukan oleh Dosen atau Pengelola Akademik.');
            }
        }

        return DB::transaction(function () use ($kelasId, $data, $userId, $dosenPembuat) {
            $quiz = Quiz::create([
                'pertemuan_id' => null,
                'kelas_id' => $kelasId,
                'tipe' => 'tryout',
                'komponen_penilaian_id' => $kelasId !== null ? ($data['komponen_penilaian_id'] ?? null) : null,
                'judul' => $data['judul'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'durasi_menit' => $data['durasi_menit'] ?? null,
                'max_attempt' => $data['max_attempt'] ?? 1,
                'passing_score' => $data['passing_score'] ?? null,
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

            if ($dosenPembuat) {
                QuizKolaborator::firstOrCreate(
                    ['quiz_id' => $quiz->id, 'dosen_id' => $dosenPembuat->id],
                    ['peran' => QuizKolaborator::PERAN_PENGINPUT_SOAL, 'ditambah_oleh' => $userId]
                );
            }

            $extraDosenIds = array_values(array_unique(array_filter(array_map('intval', $data['kolaborator_dosen_ids'] ?? []))));
            foreach ($extraDosenIds as $extraDosenId) {
                if ($dosenPembuat && $extraDosenId === (int) $dosenPembuat->id) {
                    continue;
                }
                if (Dosen::where('id', $extraDosenId)->exists()) {
                    QuizKolaborator::updateOrCreate(
                        ['quiz_id' => $quiz->id, 'dosen_id' => $extraDosenId],
                        ['peran' => QuizKolaborator::PERAN_PENGINPUT_SOAL, 'ditambah_oleh' => $userId]
                    );
                }
            }

            return $quiz->load(['kolaborators.dosen', 'kelas.mataKuliah']);
        });
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
            $viaKrs = app(LmsService::class)->isMahasiswaEnrolledInKelas($mahasiswa->id, $kelasId);
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
        ?int $kelasId = null,
        ?bool $onlyStandalone = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Quiz::where('tipe', 'tryout')
            ->with(['komponenPenilaian', 'kelas.mataKuliah', 'kelas.tahunAkademik', 'kolaborators.dosen'])
            ->withCount(['soal', 'tryoutPeserta']);

        if ($forManage && $kelasIds !== null) {
            $dosen = Dosen::where('user_id', $userId)->first();
            $query->where(function ($q) use ($kelasIds, $userId, $dosen) {
                if (!empty($kelasIds)) {
                    $q->whereIn('kelas_id', $kelasIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
                $q->orWhere(function ($standalone) use ($userId) {
                    $standalone->whereNull('kelas_id')->where('dibuat_oleh', $userId);
                });
                if ($dosen) {
                    $q->orWhereHas('kolaborators', fn ($k) => $k->where('dosen_id', $dosen->id));
                }
            });
        }

        // Tanpa filter eksplisit, batasi tryout kelas ke periode aktif (tryout ujian khusus kelas_id = null tetap tampil).
        $effectiveTahunAkademikId = $tahunAkademikId ?? \App\Models\Siakad\TahunAkademik::where('is_active', true)->value('id');

        if ($onlyStandalone === true) {
            $query->whereNull('kelas_id');
        } elseif ($onlyStandalone === false) {
            $query->whereNotNull('kelas_id');
        }

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        if ($effectiveTahunAkademikId && !$kelasId) {
            $query->where(function ($q) use ($effectiveTahunAkademikId) {
                $q->whereNull('kelas_id')
                    ->orWhereHas('kelas', function ($kelas) use ($effectiveTahunAkademikId) {
                        $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId);
                    });
            });
        } elseif ($tahunAkademikId && $kelasId) {
            $query->whereHas('kelas', function ($kelas) use ($tahunAkademikId) {
                $kelas->where('tahun_akademik_id', $tahunAkademikId);
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
                        $k->where('mahasiswa_id', $mahasiswa->id)
                            ->whereIn('status', LmsService::VALID_KRS_STATUSES)
                            ->whereNull('deleted_at');
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
        $this->assertCanManageQuiz($quiz, $olehUserId);

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
     * Tambah peserta tryout secara batch berdasarkan kombinasi:
     * - Kelas mahasiswa (mis. "25A", opsional)
     * - Program Studi (opsional)
     * - Tahun Angkatan (mis. 2025, opsional)
     * - Kelas Perkuliahan SIAKAD / KRS aktif (opsional)
     * Minimal salah satu filter harus diisi. Idempoten per mahasiswa (firstOrCreate).
     *
     * @return array{added: int, skipped: int, kelas: ?string, program_studi_id: ?int, angkatan: ?int, kelas_perkuliahan_id: ?int}
     */
    public function addTryoutPesertaByKelas(
        int $quizId,
        ?string $kelas,
        ?int $programStudiId,
        int $olehUserId,
        ?int $angkatan = null,
        ?int $kelasPerkuliahanId = null
    ): array {
        return DB::transaction(function () use ($quizId, $kelas, $programStudiId, $olehUserId, $angkatan, $kelasPerkuliahanId) {
            $quiz = Quiz::findOrFail($quizId);
            $this->assertCanManageQuiz($quiz, $olehUserId);

            if ($quiz->tipe !== 'tryout') {
                throw ValidationException::withMessages([
                    'quiz' => ['Peserta eksplisit hanya berlaku untuk tryout.'],
                ]);
            }

            $normalizedKelas = $kelas !== null && trim($kelas) !== '' ? strtoupper(trim($kelas)) : null;

            if ($normalizedKelas === null && !$programStudiId && !$angkatan && !$kelasPerkuliahanId) {
                throw ValidationException::withMessages([
                    'kelas' => ['Pilih minimal satu filter batch peserta (Kelas Mahasiswa, Program Studi, Tahun Angkatan, atau Kelas Perkuliahan).'],
                ]);
            }

            $query = Mahasiswa::query()
                ->where(function ($q) {
                    $q->where('status', 'aktif')
                        ->orWhereNull('status');
                })
                ->when($normalizedKelas !== null, fn ($q) => $q->where('kelas', $normalizedKelas))
                ->when($programStudiId, fn ($q) => $q->where('program_studi_id', $programStudiId))
                ->when($angkatan, fn ($q) => $q->where('angkatan', $angkatan))
                ->when($kelasPerkuliahanId, function ($q) use ($kelasPerkuliahanId) {
                    $q->whereHas('krs', function ($krs) use ($kelasPerkuliahanId) {
                        $krs->whereIn('status', LmsService::VALID_KRS_STATUSES)
                            ->whereNull('deleted_at')
                            ->whereHas('krsDetails', fn ($kd) => $kd->where('kelas_id', $kelasPerkuliahanId));
                    });
                });

            $mahasiswaIds = $query->pluck('id');

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

            return [
                'added' => $added,
                'skipped' => $skipped,
                'kelas' => $normalizedKelas,
                'program_studi_id' => $programStudiId,
                'angkatan' => $angkatan,
                'kelas_perkuliahan_id' => $kelasPerkuliahanId,
            ];
        });
    }

    public function removeTryoutPeserta(int $pesertaId, ?int $userId = null): bool
    {
        $peserta = TryoutPeserta::with('quiz')->findOrFail($pesertaId);
        if (($actorUserId = $userId ?? auth()->id()) && $peserta->quiz) {
            $this->assertCanManageQuiz($peserta->quiz, (int) $actorUserId);
        }

        return (bool) $peserta->delete();
    }

    /**
     * Salin peserta eksplisit dari tryout lain.
     *
     * Idempoten: peserta yang sudah terdaftar di tryout tujuan dilewati.
     * Untuk tryout yang sama-sama terikat kelas perkuliahan, wajib berada pada
     * kelas yang sama; untuk tryout ujian khusus (standalone, kelas_id null),
     * dosen wajib memiliki akses pantau/kelola pada tryout sumber.
     *
     * @return array{added: int, skipped: int, source_quiz_id: int}
     */
    public function copyTryoutPeserta(int $quizId, int $sourceQuizId, int $olehUserId): array
    {
        return DB::transaction(function () use ($quizId, $sourceQuizId, $olehUserId) {
            $target = Quiz::findOrFail($quizId);
            $source = Quiz::findOrFail($sourceQuizId);
            $this->assertCanManageQuiz($target, $olehUserId);

            if ($target->tipe !== Quiz::TIPE_TRYOUT || $source->tipe !== Quiz::TIPE_TRYOUT) {
                throw ValidationException::withMessages([
                    'source_quiz_id' => ['Salin peserta hanya berlaku antar-tryout.'],
                ]);
            }

            if ((int) $target->id === (int) $source->id) {
                throw ValidationException::withMessages([
                    'source_quiz_id' => ['Tryout sumber tidak boleh sama dengan tryout tujuan.'],
                ]);
            }

            if ($target->kelas_id && $source->kelas_id && (int) $target->kelas_id !== (int) $source->kelas_id) {
                throw ValidationException::withMessages([
                    'source_quiz_id' => ['Tryout sumber harus berada pada kelas yang sama.'],
                ]);
            }

            $this->assertCanInspectQuiz($source, $olehUserId);

            $added = 0;
            $skipped = 0;

            foreach ($source->tryoutPeserta()->pluck('mahasiswa_id') as $mahasiswaId) {
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

            return ['added' => $added, 'skipped' => $skipped, 'source_quiz_id' => (int) $source->id];
        });
    }

    public function updateQuiz(int $quizId, array $data, ?int $userId = null): Quiz
    {
        $quiz = Quiz::findOrFail($quizId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertCanManageQuiz($quiz, (int) $actorUserId);
        }
        $quiz->update($data);

        return $quiz->fresh(['komponenPenilaian']);
    }

    public function deleteQuiz(int $quizId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($quizId, $userId) {
            $quiz = Quiz::findOrFail($quizId);
            if ($actorUserId = $userId ?? auth()->id()) {
                $this->assertCanManageQuiz($quiz, (int) $actorUserId);
            }

            return $quiz->delete();
        });
    }

    /**
     * Lampirkan soal dari bank soal master ke quiz.
     */
    public function attachSoal(int $quizId, int $bankSoalId, ?int $urutan = null, ?float $poin = null, ?int $userId = null): QuizSoal
    {
        return DB::transaction(function () use ($quizId, $bankSoalId, $urutan, $poin, $userId) {
            $quiz = Quiz::findOrFail($quizId);
            if ($actorUserId = $userId ?? auth()->id()) {
                $this->assertCanManageQuiz($quiz, (int) $actorUserId);
            }
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

    public function detachSoal(int $quizSoalId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($quizSoalId, $userId) {
            $quizSoal = QuizSoal::with('quiz')->findOrFail($quizSoalId);
            if (($actorUserId = $userId ?? auth()->id()) && $quizSoal->quiz) {
                $this->assertCanManageQuiz($quizSoal->quiz, (int) $actorUserId);
            }
            $this->assertStrukturFleksibel($quizSoal->quiz);

            return $quizSoal->delete();
        });
    }

    /**
     * Lepas beberapa soal sekaligus dari satu quiz.
     *
     * Semua ID wajib milik quiz tersebut; struktur tetap terkunci bila sudah
     * ada attempt. Mengembalikan snapshot baris yang dihapus agar controller
     * dapat mencatat audit log.
     *
     * @param  array<int, int|string>  $quizSoalIds
     * @return array{deleted: int, ids: array<int, int>, old_values: array<int, array<string, mixed>>}
     */
    public function bulkDeleteSoal(int $quizId, array $quizSoalIds, ?int $userId = null): array
    {
        return DB::transaction(function () use ($quizId, $quizSoalIds, $userId) {
            $quiz = Quiz::findOrFail($quizId);
            if ($actorUserId = $userId ?? auth()->id()) {
                $this->assertCanManageQuiz($quiz, (int) $actorUserId);
            }
            $this->assertStrukturFleksibel($quiz);

            $ids = array_values(array_unique(array_map('intval', $quizSoalIds)));
            $rows = QuizSoal::where('quiz_id', $quizId)->whereIn('id', $ids)->get();

            if ($rows->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'quiz_soal_ids' => ['Salah satu soal tidak ditemukan pada tryout ini.'],
                ]);
            }

            $oldValues = $rows
                ->map(fn (QuizSoal $row) => $row->only(['id', 'quiz_id', 'bank_soal_id', 'urutan', 'poin']))
                ->all();

            QuizSoal::whereIn('id', $rows->pluck('id'))->delete();

            return [
                'deleted' => $rows->count(),
                'ids' => $rows->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'old_values' => $oldValues,
            ];
        });
    }

    /**
     * Pencarian mahasiswa untuk pemilih peserta tryout.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Mahasiswa>
     */
    public function searchMahasiswa(string $keyword, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        $keyword = trim($keyword);
        $limit = max(1, min(50, $limit));

        return Mahasiswa::query()
            ->with('programStudi:id,kode_prodi,nama,jenjang')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('nim', 'like', "%{$keyword}%")
                        ->orWhere('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get(['id', 'nim', 'nama_lengkap', 'program_studi_id', 'angkatan', 'kelas', 'status']);
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
            'kolaborators.dosen',
            'soal.bankSoal.opsi',
            'soal.bankSoal.subCpmk',
            'soal.bankSoal.rps.dosenPengembang:id,user_id,nama_lengkap,gelar_depan,gelar_belakang',
            'soal.bankSoal.pembuat:id,username,email',
            'tryoutPeserta.mahasiswa.programStudi:id,kode_prodi,nama,jenjang',
        ])->findOrFail($quizId);

        if ($actorUserId = auth()->id()) {
            $this->assertCanInspectQuiz($quiz, (int) $actorUserId);
        }

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
            ->with(['jawaban', 'quiz:id,passing_score'])
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
        $quiz = Quiz::findOrFail($quizId);
        if ($actorUserId = auth()->id()) {
            $this->assertCanInspectQuiz($quiz, (int) $actorUserId);
        }

        return QuizAttempt::where('quiz_id', $quizId)
            ->with(['mahasiswa', 'jawaban', 'quiz:id,passing_score'])
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
            $attempt = QuizAttempt::with(['jawaban', 'quiz'])->findOrFail($attemptId);
            if (($actorUserId = $olehUserId ?? auth()->id()) && $attempt->quiz) {
                $this->assertCanManageQuiz($attempt->quiz, (int) $actorUserId);
            }

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

        if (($actorUserId = auth()->id()) && $attempt->quiz) {
            $this->assertCanInspectQuiz($attempt->quiz, (int) $actorUserId);
        }

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
        if ($actorUserId = auth()->id()) {
            $this->assertCanInspectQuiz($quiz, (int) $actorUserId);
        }

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
            if ($row->attempt?->quiz) {
                $this->assertCanManageQuiz($row->attempt->quiz, $dosenUserId);
            }
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
                $q->where('mahasiswa_id', $mahasiswaId)
                    ->whereIn('status', LmsService::VALID_KRS_STATUSES)
                    ->whereNull('deleted_at');
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
        $quiz = Quiz::findOrFail($quizId);
        if ($actorUserId = auth()->id()) {
            $this->assertCanInspectQuiz($quiz, (int) $actorUserId);
        }

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
        $quiz = Quiz::findOrFail($quizId);
        if ($actorUserId = $olehUserId ?? auth()->id()) {
            $this->assertCanManageQuiz($quiz, (int) $actorUserId);
        }
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
        $kolaborator = QuizKolaborator::with('quiz')->findOrFail($kolaboratorId);
        if (($actorUserId = auth()->id()) && $kolaborator->quiz) {
            $this->assertCanManageQuiz($kolaborator->quiz, (int) $actorUserId);
        }

        return (bool) $kolaborator->delete();
    }
}
