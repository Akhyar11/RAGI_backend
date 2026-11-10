<?php

namespace App\Services\Siakad;

use App\Models\Lms\KelasLmsSetting;
use App\Models\Lms\LmsSetting;
use App\Models\Lms\MateriPertemuan;
use App\Models\Lms\MateriFile;
use App\Models\Lms\Tugas;
use App\Models\Lms\PengumpulanTugas;
use App\Models\Lms\ForumTopik;
use App\Models\Lms\IzinAbsensi;
use App\Models\Lms\LmsNotifikasi;
use App\Models\Lms\Quiz;
use App\Models\Lms\QuizAttempt;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\System\MasterReferensi;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use App\Services\Simpeg\PresensiPengajaranService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LmsService
{
    /** Status konfigurasi LMS pada rekap pengaturan (filter `status_konfigurasi`). */
    public const STATUS_PENGATURAN_SUDAH = 'terkonfigurasi';
    public const STATUS_PENGATURAN_BELUM = 'belum_terkonfigurasi';

    /** Closed-set status konfigurasi yang sah untuk filter rekap pengaturan. */
    public const STATUS_PENGATURAN = [
        self::STATUS_PENGATURAN_SUDAH,
        self::STATUS_PENGATURAN_BELUM,
    ];

    /** Status KRS yang sah untuk memberikan akses ke kelas LMS. */
    public const VALID_KRS_STATUSES = [
        'disetujui',
        'diajukan',
    ];

    public function __construct(protected FileStorageService $files) {}

    /**
     * Periksa apakah mahasiswa terdaftar aktif di kelas melalui KRS yang valid.
     */
    public function isMahasiswaEnrolledInKelas(int $mahasiswaId, int $kelasId): bool
    {
        return KrsDetail::query()
            ->where('kelas_id', $kelasId)
            ->whereHas('krs', function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId)
                    ->whereIn('status', self::VALID_KRS_STATUSES)
                    ->whereNull('deleted_at');
            })
            ->exists();
    }

    /**
     * Daftar ID kelas tempat mahasiswa terdaftar melalui KRS yang valid.
     *
     * @return array<int>
     */
    public function enrolledKelasIdsForMahasiswa(int $mahasiswaId): array
    {
        return KrsDetail::query()
            ->whereHas('krs', function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId)
                    ->whereIn('status', self::VALID_KRS_STATUSES)
                    ->whereNull('deleted_at');
            })
            ->pluck('kelas_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Periksa apakah user merupakan dosen pengampu pada kelas tertentu.
     */
    public function isDosenPengampuKelas(int $userId, int $kelasId): bool
    {
        $dosen = Dosen::where('user_id', $userId)->first();
        if (!$dosen) {
            return false;
        }

        return DosenPengampu::where('kelas_id', $kelasId)
            ->where('dosen_id', $dosen->id)
            ->exists();
    }

    /**
     * Daftar ID program studi yang dikelola secara struktural oleh dosen (Kaprodi / Admin Prodi).
     *
     * @return array<int>
     */
    public function resolveManagedProdiIds(?User $user, ?Dosen $dosen = null): array
    {
        if (!$user) {
            return [];
        }

        $dosen ??= Dosen::where('user_id', $user->id)->first();
        if (!$dosen) {
            return [];
        }

        $prodiIds = ProgramStudi::where('kaprodi_id', $dosen->id)->pluck('id')->all();

        return array_values(array_unique(array_map('intval', $prodiIds)));
    }

    /**
     * Periksa apakah user merupakan pengelola global LMS (memiliki izin kelola tanpa profil akademik).
     */
    public function isGlobalLmsManager(?User $user, ?Dosen $dosen = null, ?Mahasiswa $mahasiswa = null): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return !$dosen && !$mahasiswa && (
            $user->hasPermission('siakad.kelas.manage')
            || $user->hasPermission('lms.pengaturan.manage')
            || $user->hasPermission('lms.presensi.read')
        );
    }

    /**
     * Tentukan konteks dan kapabilitas user saat membuka sebuah kelas LMS.
     *
     * @return array{
     *     viewer_mode: string,
     *     is_pengampu: bool,
     *     is_peserta_krs: bool,
     *     is_pengelola: bool,
     *     can_teach: bool,
     *     can_configure_kelas: bool,
     *     can_participate: bool,
     *     can_export: bool
     * }
     */
    public function resolveKelasViewerContext(int $kelasId, ?int $userId = null): array
    {
        if (!$userId) {
            return [
                'viewer_mode' => 'pengelola',
                'is_pengampu' => false,
                'is_peserta_krs' => false,
                'is_pengelola' => true,
                'can_teach' => false,
                'can_configure_kelas' => false,
                'can_participate' => false,
                'can_export' => false,
            ];
        }

        $user = User::find($userId);
        $dosen = Dosen::where('user_id', $userId)->first();
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();

        $isPengampu = $dosen
            ? DosenPengampu::where('kelas_id', $kelasId)->where('dosen_id', $dosen->id)->exists()
            : false;

        $isPesertaKrs = $mahasiswa
            ? $this->isMahasiswaEnrolledInKelas($mahasiswa->id, $kelasId)
            : false;

        $isGlobalAdmin = $this->isGlobalLmsManager($user, $dosen, $mahasiswa);

        $isProdiManager = false;
        if ($user !== null && $dosen !== null) {
            $managedProdiIds = $this->resolveManagedProdiIds($user, $dosen);
            if ($managedProdiIds !== []) {
                $isProdiManager = Kelas::where('id', $kelasId)
                    ->where(function ($q) use ($managedProdiIds) {
                        $q->whereIn('program_studi_id', $managedProdiIds)
                            ->orWhereHas('programStudis', fn ($ps) => $ps->whereIn('siakad_program_studi.id', $managedProdiIds));
                    })
                    ->exists();
            }
        }

        $isPengelola = $isGlobalAdmin || $isProdiManager;

        $viewerMode = $isPengampu
            ? 'dosen'
            : ($isPesertaKrs ? 'mahasiswa' : 'pengelola');

        return [
            'viewer_mode' => $viewerMode,
            'is_pengampu' => $isPengampu,
            'is_peserta_krs' => $isPesertaKrs,
            'is_pengelola' => $isPengelola,
            'can_teach' => $isPengampu,
            'can_configure_kelas' => $isPengampu || $isGlobalAdmin,
            'can_participate' => $isPesertaKrs,
            'can_export' => $isPengampu || $isPengelola,
        ];
    }

    /**
     * Pastikan user berhak melihat kelas ini (sebagai Dosen Pengampu, Mahasiswa KRS aktif, atau Pengelola).
     */
    public function assertCanViewKelas(int $kelasId, int $userId): array
    {
        $context = $this->resolveKelasViewerContext($kelasId, $userId);

        if (!$context['is_pengampu'] && !$context['is_peserta_krs'] && !$context['is_pengelola']) {
            abort(403, 'Anda tidak memiliki akses ke kelas perkuliahan ini.');
        }

        return $context;
    }

    /**
     * Pastikan pemanggil adalah Dosen Pengampu resmi pada kelas tersebut.
     * Administrator / Super Admin atau dosen lain yang tidak mengampu kelas ini ditolak (403).
     */
    public function assertDosenPengampuKelas(int $kelasId, int $userId): void
    {
        if (!$this->isDosenPengampuKelas($userId, $kelasId)) {
            abort(403, 'Aksi akademik ini hanya dapat dilakukan oleh Dosen Pengampu kelas terkait.');
        }
    }

    /**
     * Pastikan pemanggil berhak mengubah konfigurasi LMS kelas (Dosen Pengampu atau Admin/Superadmin).
     */
    public function assertCanConfigureKelas(int $kelasId, int $userId): void
    {
        $context = $this->resolveKelasViewerContext($kelasId, $userId);
        if (!$context['can_configure_kelas']) {
            abort(403, 'Anda tidak memiliki izin untuk mengubah pengaturan LMS kelas ini.');
        }
    }

    /**
     * Pastikan pemanggil adalah Mahasiswa yang terdaftar aktif melalui KRS pada kelas tersebut.
     */
    public function assertMahasiswaPesertaKelas(int $kelasId, int $userId): Mahasiswa
    {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
        if (!$mahasiswa) {
            abort(403, 'Fitur partisipasi kelas ini hanya dapat digunakan oleh Mahasiswa.');
        }

        if (!$this->isMahasiswaEnrolledInKelas($mahasiswa->id, $kelasId)) {
            abort(403, 'Anda tidak terdaftar aktif melalui KRS pada kelas perkuliahan ini.');
        }

        return $mahasiswa;
    }

    /**
     * Pastikan fitur presensi sedang aktif.
     *
     * Feature flag `lms_fitur_presensi` (disimpan pada `core_system_settings`)
     * memungkinkan admin mematikan tampilan presensi untuk dosen & mahasiswa.
     * Admin tetap boleh mengakses (untuk keperluan support) — dibedakan lewat
     * permission `lms.presensi.manage`, bukan berdasarkan role statis.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function assertPresensiFiturAktif(?User $user = null): void
    {
        if (LmsSetting::fiturPresensiAktif()) {
            return;
        }

        $user = $user ?? auth()->user();

        if ($user instanceof User && $user->hasPermission('lms.presensi.manage')) {
            return;
        }

        abort(403, 'Fitur presensi sedang dinonaktifkan oleh administrator.');
    }

    /**
     * Simpan berkas unggahan lewat `FileStorageService`.
     *
     * Penolakan ekstensi executable oleh FileStorageService diubah menjadi
     * pesan validasi agar pengguna mendapat umpan balik yang jelas, bukan
     * kesalahan server 500.
     *
     * @throws ValidationException
     */
    protected function storeUploadedFile(UploadedFile $file, string $baseDir, string $disk): string
    {
        try {
            return $this->files->store($file, $baseDir, $disk);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'file' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Dapatkan disk storage yang aktif (prioritas: kelas -> system setting -> default disk).
     *
     * Optimal R2: bila kandidat disk cloud (r2/r2-private/s3) belum dikonfigurasi
     * (bucket/endpoint kosong, mis. env local), otomatis fallback ke kandidat
     * berikutnya agar upload tetap jalan di storage lokal.
     */
    public function resolveDisk(?Kelas $kelas = null): string
    {
        $candidates = [];

        if ($kelas && $kelas->lmsSetting && !empty($kelas->lmsSetting->storage_disk)) {
            $candidates[] = $kelas->lmsSetting->storage_disk;
        }

        $configuredDisk = SystemSetting::get('lms_storage_disk');
        if ($configuredDisk) {
            $candidates[] = $configuredDisk;
        }

        $candidates[] = config('filesystems.default', 'local');

        $availableDisks = config('filesystems.disks', []);

        foreach ($candidates as $disk) {
            if (!is_string($disk) || !array_key_exists($disk, $availableDisks)) {
                continue;
            }

            if ($this->isCloudDisk($disk) && !$this->isCloudDiskConfigured($disk)) {
                continue;
            }

            return $disk;
        }

        return 'local';
    }

    /**
     * True bila disk memakai driver cloud S3-compatible (R2/S3).
     */
    protected function isCloudDisk(string $disk): bool
    {
        return in_array(strtolower($disk), ['r2', 'r2-private', 's3'], true);
    }

    /**
     * True bila disk cloud sudah punya bucket + endpoint minimal.
     * Cerminan FileStorageService::isCloudDiskConfigured agar konsisten.
     */
    protected function isCloudDiskConfigured(string $disk): bool
    {
        $disk = strtolower($disk);

        if ($disk === 's3') {
            return (bool) config('filesystems.disks.s3.bucket');
        }

        if (in_array($disk, ['r2', 'r2-private'], true)) {
            $cfg = config("filesystems.disks.{$disk}", []);

            return !empty($cfg['bucket']) && !empty($cfg['endpoint']);
        }

        return true;
    }

    /**
     * Dapatkan overview kelas LMS beserta 16 pertemuan, progres, dan statistik.
     */
    public function getKelasOverview(int $kelasId, ?int $userId = null): array
    {
        $kelas = Kelas::with([
            'mataKuliah',
            'tahunAkademik',
            'programStudi',
            'ruangan',
            'dosenPengampu.dosen',
            'lmsSetting',
            'pertemuans' => function ($q) {
                $q->orderBy('pertemuan_ke')
                  ->withCount([
                      'materiList',
                      'tugasList',
                      'absensi as absensi_count' => fn ($absensi) => $absensi->where('is_submitted', true),
                      'absensi as hadir_count' => fn ($absensi) => $absensi
                          ->where('is_submitted', true)
                          ->where('status', AbsensiMahasiswa::STATUS_HADIR),
                  ]);
            },
        ])->findOrFail($kelasId);

        $viewerContext = $userId !== null
            ? $this->assertCanViewKelas($kelasId, $userId)
            : $this->resolveKelasViewerContext($kelasId, null);

        // Ambil mahasiswa peserta kelas dari KRS disetujui
        $totalMahasiswa = KrsDetail::query()
            ->join('siakad_krs', 'siakad_krs.id', '=', 'siakad_krs_detail.krs_id')
            ->where('siakad_krs_detail.kelas_id', $kelasId)
            ->whereIn('siakad_krs.status', ['disetujui', 'diajukan'])
            ->whereNull('siakad_krs.deleted_at')
            ->distinct()
            ->count('siakad_krs.mahasiswa_id');

        // Hitung statistik pertemuan
        $totalPertemuan = $kelas->pertemuans->count();
        $pertemuanTerisi = $kelas->pertemuans->filter(function ($p) use ($totalMahasiswa) {
            return $p->materi_list_count > 0
                || ($totalMahasiswa > 0 && $p->absensi_count >= $totalMahasiswa);
        })->count();

        // Hitung rata-rata kehadiran jika ada absensi
        $totalAbsen = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
            ->where('is_submitted', true)
            ->count();
        $totalHadir = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
            ->where('is_submitted', true)
            ->where('status', 'hadir')
            ->count();
        $rataHadirPersen = $totalAbsen > 0 ? round(($totalHadir / $totalAbsen) * 100, 1) : 0;

        // Total tugas aktif
        $totalTugas = Tugas::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))->count();

        // Total materi & izin pending untuk kartu statistik
        $pertemuanIds = $kelas->pertemuans->pluck('id');
        $totalMateri = MateriPertemuan::whereIn('pertemuan_id', $pertemuanIds)->count();
        $totalIzinPending = IzinAbsensi::whereIn('pertemuan_id', $pertemuanIds)
            ->where('status', 'pending')->count();

        // Daftar pertemuan untuk tab Perkuliahan (kontrak frontend: pertemuan_list).
        $pertemuanList = $kelas->pertemuans->map(function ($p) {
            $tokenAktif = !empty($p->token_absensi)
                && $p->token_expired_at
                && !$p->presensi_closed_at
                && now()->lt($p->token_expired_at);

            return [
                'id' => $p->id,
                'kelas_id' => $p->kelas_id,
                'pertemuan_ke' => $p->pertemuan_ke,
                'tanggal' => $p->tanggal,
                'materi' => $p->materi,
                'catatan_pertemuan' => $p->catatan_pertemuan,
                'jam_mulai' => $p->jam_mulai,
                'jam_selesai' => $p->jam_selesai,
                'status_pertemuan' => $p->status_pertemuan,
                'token_absensi' => $p->token_absensi,
                'token_expired_at' => $p->token_expired_at,
                'is_token_active' => (bool) $tokenAktif,
                'materi_list_count' => $p->materi_list_count ?? 0,
                'tugas_list_count' => $p->tugas_list_count ?? 0,
                'hadir_count' => $p->hadir_count ?? 0,
            ];
        })->values()->all();

        $pertemuanSelesai = $kelas->pertemuans->where('status_pertemuan', 'selesai')->count();
        $pertemuanBerjalan = $kelas->pertemuans->where('status_pertemuan', 'berlangsung')->count();

        $komponenObe = \App\Models\Siakad\KomponenPenilaian::where('kelas_id', $kelasId)
            ->where('is_aktif', true)
            ->orderBy('urutan')
            ->get(['id', 'nama_komponen', 'bobot']);

        return [
            'kelas' => $kelas,
            'viewer_context' => $viewerContext,
            'lms_setting' => $kelas->lmsSetting ?? $this->getDefaultKelasSetting($kelas),
            'komponen_obe' => $komponenObe,
            'pertemuan_list' => $pertemuanList,
            'progress' => [
                'total' => $totalPertemuan,
                'terisi' => $pertemuanTerisi,
                'persen' => $totalPertemuan > 0 ? round(($pertemuanTerisi / $totalPertemuan) * 100) : 0,
                // Alias kontrak frontend (tab Perkuliahan & kartu progres)
                'total_pertemuan_terencana' => $totalPertemuan,
                'pertemuan_selesai' => $pertemuanSelesai,
                'pertemuan_berjalan' => $pertemuanBerjalan,
                'persentase_selesai' => $totalPertemuan > 0 ? round(($pertemuanTerisi / $totalPertemuan) * 100) : 0,
            ],
            'statistik' => [
                'total_mahasiswa' => $totalMahasiswa,
                'total_tugas' => $totalTugas,
                'rata_hadir_persen' => $rataHadirPersen,
                // Alias kontrak frontend
                'total_materi' => $totalMateri,
                'total_izin_pending' => $totalIzinPending,
                'total_mahasiswa_krs' => $totalMahasiswa,
            ],
        ];
    }

    /**
     * Dapatkan detail pertemuan LMS (Materi, Tugas, Absensi, dan Izin).
     */
    public function getPertemuanDetail(int $pertemuanId, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::with([
            'kelas.mataKuliah',
            'kelas.lmsSetting',
            'materiList.files',
            'tugasList.komponenPenilaian',
            'tugasList.pengumpulan.mahasiswa',
            'quizList.komponenPenilaian',
            'quizList.soal',
            'absensi.mahasiswa',
            'izinAbsensiList.mahasiswa',
        ])->findOrFail($pertemuanId);

        $viewerContext = $userId !== null
            ? $this->assertCanViewKelas((int) $pertemuan->kelas_id, $userId)
            : $this->resolveKelasViewerContext((int) $pertemuan->kelas_id, null);

        $isMahasiswa = $viewerContext['viewer_mode'] === 'mahasiswa';
        $mahasiswaModel = null;
        if ($isMahasiswa && $userId) {
            $mahasiswaModel = Mahasiswa::where('user_id', $userId)->first();
        }

        // Jika mahasiswa, filter hanya materi dan tugas yang sudah dipublish
        $materiList = $pertemuan->materiList;
        $tugasList = $pertemuan->tugasList;
        $quizList = $pertemuan->quizList;
        if ($isMahasiswa) {
            $materiList = $materiList->where('is_published', true)->values();
            $tugasList = $tugasList->where('is_published', true)->values();
            $quizList = $quizList->where('is_published', true)->values();
        }

        $submittedAbsensi = $pertemuan->absensi->where('is_submitted', true)->values();
        $absensiList = $submittedAbsensi;

        // Status absensi mahasiswa login
        $myAbsensi = null;
        $myPengumpulan = [];
        $myIzin = null;
        if ($mahasiswaModel) {
            $myAbsensi = $submittedAbsensi->firstWhere('mahasiswa_id', $mahasiswaModel->id);
            $myIzin = $pertemuan->izinAbsensiList->firstWhere('mahasiswa_id', $mahasiswaModel->id);
            foreach ($tugasList as $tugas) {
                $sub = $tugas->pengumpulan->firstWhere('mahasiswa_id', $mahasiswaModel->id);
                if ($sub) {
                    $myPengumpulan[$tugas->id] = $sub;
                }
            }
        }

        // Summary absensi per pertemuan
        $absensiSummary = [
            'hadir' => $submittedAbsensi->where('status', 'hadir')->count(),
            'sakit' => $submittedAbsensi->where('status', 'sakit')->count(),
            'izin'  => $submittedAbsensi->where('status', 'izin')->count(),
            'alfa'  => $submittedAbsensi->where('status', 'alfa')->count(),
            'total' => $submittedAbsensi->count(),
        ];

        if (!$isMahasiswa) {
            $roster = KrsDetail::query()
                ->where('kelas_id', $pertemuan->kelas_id)
                ->whereHas('krs', fn ($krs) => $krs->whereIn('status', ['disetujui', 'diajukan']))
                ->with('krs.mahasiswa:id,nim,nama_lengkap')
                ->get()
                ->map(fn (KrsDetail $detail) => $detail->krs?->mahasiswa)
                ->filter()
                ->unique('id')
                ->sortBy('nama_lengkap')
                ->values();

            $absensiByMahasiswa = $submittedAbsensi->keyBy('mahasiswa_id');
            $absensiList = $roster->map(function (Mahasiswa $mahasiswa) use ($pertemuan, $absensiByMahasiswa) {
                $attendance = $absensiByMahasiswa->get($mahasiswa->id);

                return [
                    'id' => $attendance?->id,
                    'pertemuan_id' => $pertemuan->id,
                    'mahasiswa_id' => $mahasiswa->id,
                    'nama_lengkap' => $mahasiswa->nama_lengkap,
                    'nim' => $mahasiswa->nim,
                    'status' => $attendance?->status,
                    'catatan' => $attendance?->catatan,
                    'is_submitted' => $attendance !== null,
                ];
            });
        }

        // Status token aktif
        $tokenAktif = !empty($pertemuan->token_absensi) &&
            $pertemuan->token_expired_at &&
            !$pertemuan->presensi_closed_at &&
            now()->lt($pertemuan->token_expired_at);

        // Privasi data: mahasiswa hanya boleh melihat catatan miliknya sendiri,
        // bukan absensi/izin/pengumpulan seluruh kelas.
        if ($isMahasiswa && $mahasiswaModel) {
            $absensiList = $submittedAbsensi
                ->where('mahasiswa_id', $mahasiswaModel->id)
                ->values();
            $pertemuan->setRelation(
                'absensi',
                $absensiList
            );
            $pertemuan->setRelation(
                'izinAbsensiList',
                $pertemuan->izinAbsensiList->where('mahasiswa_id', $mahasiswaModel->id)->values()
            );
            foreach ($tugasList as $tugas) {
                $tugas->setRelation(
                    'pengumpulan',
                    $tugas->pengumpulan->where('mahasiswa_id', $mahasiswaModel->id)->values()
                );
            }
        }

        return [
            'pertemuan' => $pertemuan,
            'viewer_context' => $viewerContext,
            'materi_list' => $materiList,
            'tugas_list' => $tugasList,
            'quiz_list' => $quizList->values(),
            'komponen_obe_list' => \App\Models\Siakad\KomponenPenilaian::where('kelas_id', $pertemuan->kelas_id)
                ->where('is_aktif', true)
                ->orderBy('urutan')
                ->get(['id', 'nama_komponen', 'bobot']),
            'absensi_list' => $absensiList,
            'absensi_summary' => $absensiSummary,
            'izin_list' => $pertemuan->izinAbsensiList,
            'token_aktif' => $tokenAktif,
            'token_sisa_detik' => $tokenAktif ? now()->diffInSeconds($pertemuan->token_expired_at) : 0,
            'presensi_ditutup' => $pertemuan->presensi_closed_at !== null,
            'presensi_closed_at' => $pertemuan->presensi_closed_at?->toIso8601String(),
            'my_absensi' => $myAbsensi,
            'my_pengumpulan' => $myPengumpulan,
            'my_izin' => $myIzin,
        ];
    }

    /**
     * Snapshot roster presensi untuk refresh ringan pada layar dosen.
     */
    public function getPertemuanAbsensiRoster(int $pertemuanId, int $userId): array
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        $viewerContext = $this->assertCanViewKelas((int) $pertemuan->kelas_id, $userId);

        if ($viewerContext['viewer_mode'] === 'mahasiswa') {
            abort(403, 'Daftar presensi kelas hanya dapat dilihat oleh Dosen Pengampu atau pengelola.');
        }

        $roster = KrsDetail::query()
            ->where('kelas_id', $pertemuan->kelas_id)
            ->whereHas('krs', fn ($krs) => $krs->whereIn('status', self::VALID_KRS_STATUSES))
            ->with('krs.mahasiswa:id,nim,nama_lengkap')
            ->get()
            ->map(fn (KrsDetail $detail) => $detail->krs?->mahasiswa)
            ->filter()
            ->unique('id')
            ->sortBy('nama_lengkap')
            ->values();

        $attendanceByMahasiswa = AbsensiMahasiswa::query()
            ->where('pertemuan_id', $pertemuanId)
            ->where('is_submitted', true)
            ->get()
            ->keyBy('mahasiswa_id');

        $statusLabels = MasterReferensi::query()
            ->where('modul', 'siakad')
            ->where('tipe', 'status_absensi')
            ->pluck('nama', 'kode');

        $absensiList = $roster->map(function (Mahasiswa $mahasiswa) use ($attendanceByMahasiswa, $statusLabels) {
            $attendance = $attendanceByMahasiswa->get($mahasiswa->id);

            return [
                'id' => $attendance?->id,
                'mahasiswa_id' => $mahasiswa->id,
                'nama_lengkap' => $mahasiswa->nama_lengkap,
                'nim' => $mahasiswa->nim,
                'status' => $attendance?->status,
                'status_label' => $attendance
                    ? ($statusLabels[$attendance->status] ?? str($attendance->status)->replace('_', ' ')->title()->toString())
                    : null,
                'catatan' => $attendance?->catatan,
                'is_submitted' => $attendance !== null,
                'waktu_absen' => $attendance?->updated_at?->toIso8601String(),
            ];
        });

        return [
            'pertemuan_id' => $pertemuanId,
            'absensi_list' => $absensiList,
            'total_peserta' => $absensiList->count(),
            'total_sudah_absen' => $absensiList->where('is_submitted', true)->count(),
            'total_belum_absen' => $absensiList->where('is_submitted', false)->count(),
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Buat materi pembelajaran baru.
     */
    public function storeMateri(int $pertemuanId, array $data, ?UploadedFile $file = null, ?int $userId = null): MateriPertemuan
    {
        return DB::transaction(function () use ($pertemuanId, $data, $file, $userId) {
            $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
            if ($actorUserId = $userId ?? auth()->id()) {
                $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
            }

            $tipeKontenId = $data['tipe_konten_id'] ?? null;
            $tipeKonten   = $data['tipe_konten'] ?? null;
            if ($tipeKontenId && !$tipeKonten) {
                $ref = DB::table('spmb_master_referensi')->find($tipeKontenId);
                $tipeKonten = $ref?->kode ?? 'teks';
            }

            $materi = MateriPertemuan::create([
                'pertemuan_id'   => $pertemuanId,
                'tipe_konten_id' => $tipeKontenId,
                'judul'          => $data['judul'],
                'deskripsi'      => $data['deskripsi'] ?? null,
                'tipe_konten'    => $tipeKonten ?? 'teks',
                'link_eksternal' => $data['link_eksternal'] ?? null,
                'urutan'         => $data['urutan'] ?? ((MateriPertemuan::where('pertemuan_id', $pertemuanId)->max('urutan') ?? 0) + 1),
                'is_published'   => $data['is_published'] ?? true,
            ]);

            // Jika ada berkas di-upload bersamaan
            if ($file) {
                $this->uploadMateriFile($materi->id, $file, $userId);
            }

            return $materi->load('files');
        });
    }

    /**
     * Perbarui data materi pembelajaran.
     */
    public function updateMateri(int $materiId, array $data, ?int $userId = null): MateriPertemuan
    {
        if (isset($data['tipe_konten_id']) && !isset($data['tipe_konten'])) {
            $ref = DB::table('spmb_master_referensi')->find($data['tipe_konten_id']);
            $data['tipe_konten'] = $ref?->kode ?? 'teks';
        }

        $materi = MateriPertemuan::with('pertemuan')->findOrFail($materiId);
        if (($actorUserId = $userId ?? auth()->id()) && $materi->pertemuan) {
            $this->assertDosenPengampuKelas((int) $materi->pertemuan->kelas_id, (int) $actorUserId);
        }

        $materi->update($data);

        return $materi->fresh(['files']);
    }

    /**
     * Hapus materi beserta berkas fisiknya.
     */
    public function deleteMateri(int $materiId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($materiId, $userId) {
            $materi = MateriPertemuan::with(['files', 'pertemuan'])->findOrFail($materiId);
            if (($actorUserId = $userId ?? auth()->id()) && $materi->pertemuan) {
                $this->assertDosenPengampuKelas((int) $materi->pertemuan->kelas_id, (int) $actorUserId);
            }

            foreach ($materi->files as $file) {
                if ($file->file_path && Storage::disk($file->disk)->exists($file->file_path)) {
                    Storage::disk($file->disk)->delete($file->file_path);
                }
                $file->delete();
            }

            return $materi->delete();
        });
    }

    /**
     * Upload lampiran berkas materi tambahan.
     *
     * Penyimpanan WAJIB lewat `FileStorageService` agar nama berkas selalu
     * UUID + ekstensi asli, path DB relatif, dan ekstensi executable ditolak.
     */
    public function uploadMateriFile(int $materiId, UploadedFile $file, ?int $userId = null): MateriFile
    {
        $materi = MateriPertemuan::with('pertemuan.kelas')->findOrFail($materiId);
        if (($actorUserId = $userId ?? auth()->id()) && $materi->pertemuan) {
            $this->assertDosenPengampuKelas((int) $materi->pertemuan->kelas_id, (int) $actorUserId);
        }

        $disk = $this->resolveDisk($materi->pertemuan?->kelas);

        $storedPath = $this->storeUploadedFile($file, 'siakad/lms/materi', $disk);

        return MateriFile::create([
            'materi_id'      => $materiId,
            'nama_file'      => $file->getClientOriginalName(),
            'file_path'      => $storedPath,
            'disk'           => $disk,
            'ukuran_bytes'   => $file->getSize(),
            'mime_type'      => $file->getClientMimeType(),
        ]);
    }

    /**
     * Hapus satu berkas lampiran materi.
     */
    public function deleteMateriFile(int $fileId, ?int $userId = null): bool
    {
        $file = MateriFile::with('materi.pertemuan')->findOrFail($fileId);
        if (($actorUserId = $userId ?? auth()->id()) && $file->materi?->pertemuan) {
            $this->assertDosenPengampuKelas((int) $file->materi->pertemuan->kelas_id, (int) $actorUserId);
        }

        $this->files->delete($file->file_path, $file->disk);

        return $file->delete();
    }

    /**
     * Buat penugasan baru pada pertemuan tertentu.
     */
    public function storeTugas(int $pertemuanId, array $data, ?int $userId = null): Tugas
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        return Tugas::create([
            'pertemuan_id'          => $pertemuanId,
            'komponen_penilaian_id' => $data['komponen_penilaian_id'] ?? null,
            'judul'                 => $data['judul'],
            'deskripsi'             => $data['deskripsi'] ?? null,
            'deadline_at'           => $data['deadline_at'] ?? null,
            'maks_nilai'            => $data['maks_nilai'] ?? 100,
            'can_submit_late'       => $data['can_submit_late'] ?? true,
            'is_published'          => $data['is_published'] ?? true,
        ]);
    }

    /**
     * Perbarui data tugas perkuliahan.
     */
    public function updateTugas(int $tugasId, array $data, ?int $userId = null): Tugas
    {
        $tugas = Tugas::with('pertemuan')->findOrFail($tugasId);
        if (($actorUserId = $userId ?? auth()->id()) && $tugas->pertemuan) {
            $this->assertDosenPengampuKelas((int) $tugas->pertemuan->kelas_id, (int) $actorUserId);
        }

        $tugas->update($data);

        return $tugas->fresh();
    }

    /**
     * Hapus penugasan dan seluruh berkas pengumpulan mahasiswa.
     */
    public function deleteTugas(int $tugasId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($tugasId, $userId) {
            $tugas = Tugas::with(['pengumpulan', 'pertemuan'])->findOrFail($tugasId);
            if (($actorUserId = $userId ?? auth()->id()) && $tugas->pertemuan) {
                $this->assertDosenPengampuKelas((int) $tugas->pertemuan->kelas_id, (int) $actorUserId);
            }

            foreach ($tugas->pengumpulan as $sub) {
                if ($sub->file_path && Storage::disk($sub->disk)->exists($sub->file_path)) {
                    Storage::disk($sub->disk)->delete($sub->file_path);
                }
                $sub->delete();
            }

            return $tugas->delete();
        });
    }

    /**
     * Simpan pengumpulan tugas oleh mahasiswa.
     */
    public function kumpulkanTugas(int $tugasId, int $userId, array $data, ?UploadedFile $file = null): PengumpulanTugas
    {
        return DB::transaction(function () use ($tugasId, $userId, $data, $file) {
            $tugas = Tugas::with('pertemuan.kelas')->findOrFail($tugasId);
            $kelasId = (int) ($tugas->pertemuan?->kelas_id ?? 0);
            $mahasiswa = $this->assertMahasiswaPesertaKelas($kelasId, $userId);
            $mahasiswaId = $mahasiswa->id;

            $disk = $this->resolveDisk($tugas->pertemuan?->kelas);

            $isLate = $this->hitungTerlambat($tugas);

            $pengumpulan = PengumpulanTugas::firstOrNew([
                'tugas_id'     => $tugasId,
                'mahasiswa_id' => $mahasiswaId,
            ]);

            // Pengumpulan yang sudah dinilai dikunci — mahasiswa tidak boleh
            // menimpa berkas yang sudah dinilai dosen.
            $this->assertBelumDinilai($pengumpulan);

            // Jika ada file lama dan di-upload file baru, hapus berkas lama
            if ($file) {
                $this->files->delete($pengumpulan->file_path, $pengumpulan->disk);

                $storedPath = $this->storeUploadedFile($file, 'siakad/lms/pengumpulan', $disk);

                $pengumpulan->file_path = $storedPath;
                $pengumpulan->disk = $disk;
                $pengumpulan->nama_file_asli = $file->getClientOriginalName();
                $pengumpulan->ukuran_bytes = $file->getSize();
                $pengumpulan->mime_type = $file->getClientMimeType();
            }

            $pengumpulan->catatan_mahasiswa = $data['catatan_mahasiswa'] ?? $pengumpulan->catatan_mahasiswa;
            $pengumpulan->is_late = $isLate;
            $pengumpulan->save();

            return $pengumpulan->fresh();
        });
    }

    /**
     * Perbarui pengumpulan tugas yang sudah ada (file &/atau catatan mahasiswa).
     *
     * Hanya pemilik pengumpulan (mahasiswaConcerned) yang boleh mengubah, dan
     * hanya bila belum dinilai. Batas waktu & izin pengumpulan terlambat
     * mengikuti aturan yang sama dengan pengiriman pertama.
     */
    public function updatePengumpulan(int $tugasId, int $userId, array $data, ?UploadedFile $file = null): PengumpulanTugas
    {
        return DB::transaction(function () use ($tugasId, $userId, $data, $file) {
            $tugas = Tugas::with('pertemuan.kelas')->findOrFail($tugasId);
            $kelasId = (int) ($tugas->pertemuan?->kelas_id ?? 0);
            $mahasiswa = $this->assertMahasiswaPesertaKelas($kelasId, $userId);

            $pengumpulan = PengumpulanTugas::where('tugas_id', $tugasId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->first();

            if (!$pengumpulan) {
                throw ValidationException::withMessages([
                    'pengumpulan' => ['Belum ada pengumpulan tugas ini. Gunakan endpoint kirim pengumpulan terlebih dahulu.'],
                ]);
            }

            $this->assertBelumDinilai($pengumpulan);

            $isLate = $this->hitungTerlambat($tugas);
            $disk = $this->resolveDisk($tugas->pertemuan?->kelas);

            if ($file) {
                $this->files->delete($pengumpulan->file_path, $pengumpulan->disk);

                $storedPath = $this->storeUploadedFile($file, 'siakad/lms/pengumpulan', $disk);

                $pengumpulan->file_path = $storedPath;
                $pengumpulan->disk = $disk;
                $pengumpulan->nama_file_asli = $file->getClientOriginalName();
                $pengumpulan->ukuran_bytes = $file->getSize();
                $pengumpulan->mime_type = $file->getClientMimeType();
            }

            if (array_key_exists('catatan_mahasiswa', $data)) {
                $pengumpulan->catatan_mahasiswa = $data['catatan_mahasiswa'];
            }

            $pengumpulan->is_late = $isLate;
            $pengumpulan->save();

            return $pengumpulan->fresh();
        });
    }

    /**
     * Tarik kembali / hapus pengumpulan tugas oleh pemiliknya.
     *
     * Hanya boleh sebelum dinilai. Berkas fisik ikut dihapus.
     */
    public function hapusPengumpulan(int $tugasId, int $userId): bool
    {
        return DB::transaction(function () use ($tugasId, $userId) {
            $tugas = Tugas::with('pertemuan')->findOrFail($tugasId);
            $kelasId = (int) ($tugas->pertemuan?->kelas_id ?? 0);
            $mahasiswa = $this->assertMahasiswaPesertaKelas($kelasId, $userId);

            $pengumpulan = PengumpulanTugas::where('tugas_id', $tugasId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->first();

            if (!$pengumpulan) {
                throw ValidationException::withMessages([
                    'pengumpulan' => ['Tidak ada pengumpulan tugas ini untuk dihapus.'],
                ]);
            }

            $this->assertBelumDinilai($pengumpulan);

            $this->files->delete($pengumpulan->file_path, $pengumpulan->disk);

            return (bool) $pengumpulan->delete();
        });
    }

    /**
     * True bila pengumpulan mengirim lewat deadline (dan tugas mengizinkan).
     *
     * Melempar ValidationException bila deadline lewat DAN tugas tidak
     * mengizinkan pengumpulan terlambat.
     */
    protected function hitungTerlambat(Tugas $tugas): bool
    {
        if (!$tugas->deadline_at || !now()->gt($tugas->deadline_at)) {
            return false;
        }

        if (!$tugas->can_submit_late) {
            throw ValidationException::withMessages([
                'deadline_at' => ['Batas waktu pengumpulan tugas ini telah berakhir dan tidak menerima pengumpulan terlambat.'],
            ]);
        }

        return true;
    }

    /**
     * Pengumpulan yang sudah dinilai tidak boleh diubah/diubah oleh mahasiswa.
     */
    protected function assertBelumDinilai(PengumpulanTugas $pengumpulan): void
    {
        if ($pengumpulan->nilai !== null) {
            throw ValidationException::withMessages([
                'pengumpulan' => ['Pengumpulan tugas ini sudah dinilai dan tidak dapat diubah.'],
            ]);
        }
    }

    /**
     * Beri nilai tugas dan otomatis sinkronisasikan ke OBE jika terhubung komponen penilaian.
     */
    public function beriNilaiTugas(int $pengumpulanId, float $nilai, ?string $feedback, int $dosenUserId): PengumpulanTugas
    {
        return DB::transaction(function () use ($pengumpulanId, $nilai, $feedback, $dosenUserId) {
            $pengumpulan = PengumpulanTugas::with('tugas.pertemuan.kelas')->findOrFail($pengumpulanId);
            $tugas = $pengumpulan->tugas;

            if ($tugas?->pertemuan?->kelas_id) {
                $this->assertDosenPengampuKelas((int) $tugas->pertemuan->kelas_id, $dosenUserId);
            }

            if ($nilai < 0 || $nilai > $tugas->maks_nilai) {
                throw ValidationException::withMessages([
                    'nilai' => ["Nilai harus berada di antara 0 dan {$tugas->maks_nilai}."],
                ]);
            }

            $pengumpulan->update([
                'nilai'          => $nilai,
                'feedback_dosen' => $feedback,
                'dinilai_at'     => now(),
                'dinilai_oleh'   => $dosenUserId,
            ]);

            // Sinkronisasi ke siakad_nilai_komponen_mhs jika ada komponen OBE yang terhubung
            if ($tugas->komponen_penilaian_id && $tugas->pertemuan?->kelas_id) {
                $kelasId = $tugas->pertemuan->kelas_id;
                $mahasiswaId = $pengumpulan->mahasiswa_id;

                // Cari KRS Detail mahasiswa untuk kelas ini
                $krsDetail = KrsDetail::where('kelas_id', $kelasId)
                    ->whereHas('krs', function ($q) use ($mahasiswaId) {
                        $q->where('mahasiswa_id', $mahasiswaId)
                            ->whereIn('status', self::VALID_KRS_STATUSES)
                            ->whereNull('deleted_at');
                    })
                    ->first();

                if ($krsDetail) {
                    NilaiKomponenMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id'         => $krsDetail->id,
                            'komponen_penilaian_id' => $tugas->komponen_penilaian_id,
                        ],
                        [
                            'nilai_angka'           => $nilai,
                            'catatan_feedback'      => $feedback,
                            'diinput_oleh'          => $dosenUserId,
                        ]
                    );
                }
            }

            return $pengumpulan->fresh(['tugas', 'mahasiswa']);
        });
    }

    public function buildPresensiQrPayload(int $pertemuanId, string $token): string
    {
        return "RAG-PRESENSI:{$pertemuanId}:{$token}";
    }

    /**
     * Generate 6-digit token absensi realtime dengan masa aktif berbatas.
     * Membuka/membuka-ulang sesi: reset penanda tutup + set jendela sesi.
     */
    public function generateTokenAbsensi(int $pertemuanId, ?int $windowMenit = null, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::with('kelas.lmsSetting')->findOrFail($pertemuanId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        if ($pertemuan->presensi_closed_at) {
            throw ValidationException::withMessages([
                'pertemuan' => ['Sesi presensi sudah ditutup. Buka ulang sesi bila ingin menerbitkan token baru.'],
            ]);
        }

        $ttlMinutes = (int) SystemSetting::get('lms_token_ttl_minutes', 15);
        if ($ttlMinutes < 1) {
            $ttlMinutes = 15;
        }

        $rotateSeconds = (int) SystemSetting::get('lms_token_rotate_seconds', 30);
        if ($rotateSeconds < 10) {
            $rotateSeconds = 30;
        }

        $defaultWindow = (int) SystemSetting::get('lms_presensi_window_menit', 30);
        if ($defaultWindow < 5) {
            $defaultWindow = 30;
        }
        $window = $windowMenit !== null ? max(5, min(180, $windowMenit)) : $defaultWindow;

        if (!empty($pertemuan->token_absensi)) {
            Cache::put("lms:presensi:prev_token:{$pertemuanId}", (string) $pertemuan->token_absensi, now()->addSeconds(10));
        }

        // Generate 6 digit angka acak
        $token = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = now()->addMinutes($ttlMinutes);

        $pertemuan->update([
            'token_absensi'      => $token,
            'token_expired_at'   => $expiredAt,
            'token_rotated_at'   => now(),
            'window_menit'       => $window,
            'presensi_closed_at' => null,
            'status_pertemuan'   => 'berlangsung',
        ]);

        return [
            'pertemuan_id'            => $pertemuanId,
            'kelas_id'                => (int) $pertemuan->kelas_id,
            'token'                   => $token,
            'qr_payload'              => $this->buildPresensiQrPayload($pertemuanId, $token),
            'rotate_interval_seconds' => $rotateSeconds,
            'ttl_menit'               => $ttlMinutes,
            'window_menit'            => $window,
            'expired_at'              => $expiredAt->toIso8601String(),
            'sisa_detik'              => $ttlMinutes * 60,
        ];
    }

    /**
     * Putar ulang token absensi dengan umur pendek (anti titip-hadir:
     * kode/QR berubah tiap 30 detik selama layar presensi dosen terbuka).
     * Durasi diambil dari `lms_token_rotate_seconds` (default 30 detik).
     */
    public function rotateTokenAbsensi(int $pertemuanId, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        if ($pertemuan->presensi_closed_at) {
            throw ValidationException::withMessages([
                'pertemuan' => ['Sesi presensi sudah ditutup. Token tidak dapat diputar ulang.'],
            ]);
        }

        $rotateSeconds = (int) SystemSetting::get('lms_token_rotate_seconds', 30);
        if ($rotateSeconds < 10) {
            $rotateSeconds = 30;
        }

        if (!empty($pertemuan->token_absensi)) {
            Cache::put("lms:presensi:prev_token:{$pertemuanId}", (string) $pertemuan->token_absensi, now()->addSeconds(10));
        }

        $token = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = now()->addSeconds($rotateSeconds);

        $pertemuan->update([
            'token_absensi'    => $token,
            'token_expired_at' => $expiredAt,
            'token_rotated_at' => now(),
            'status_pertemuan' => 'berlangsung',
        ]);

        return [
            'pertemuan_id'            => $pertemuanId,
            'kelas_id'                => (int) $pertemuan->kelas_id,
            'token'                   => $token,
            'qr_payload'              => $this->buildPresensiQrPayload($pertemuanId, $token),
            'rotate_interval_seconds' => $rotateSeconds,
            'ttl_detik'               => $rotateSeconds,
            'expired_at'              => $expiredAt->toIso8601String(),
            'sisa_detik'              => $rotateSeconds,
        ];
    }

    /**
     * Dosen mengisi jurnal pertemuan sekaligus membuka sesi absensi (token & QR rotasi 30 detik).
     */
    public function bukaSesiPresensiDenganJurnal(int $pertemuanId, array $data, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::with('kelas.lmsSetting')->findOrFail($pertemuanId);
        $actorUserId = (int) ($userId ?? auth()->id());
        $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, $actorUserId);
        app(PresensiPengajaranService::class)->assertWriteAllowed($pertemuan, $actorUserId);

        $materi = trim((string) ($data['materi'] ?? $pertemuan->materi ?? ''));
        if ($materi === '') {
            throw ValidationException::withMessages([
                'materi' => ['Pokok bahasan / jurnal materi pertemuan wajib diisi sebelum membuka sesi presensi.'],
            ]);
        }

        $pertemuan->update([
            'materi'             => $materi,
            'catatan_pertemuan'  => array_key_exists('catatan_pertemuan', $data) ? $data['catatan_pertemuan'] : $pertemuan->catatan_pertemuan,
            'tanggal'            => !empty($data['tanggal']) ? $data['tanggal'] : ($pertemuan->tanggal ?: now()->toDateString()),
            'jam_mulai'          => array_key_exists('jam_mulai', $data) ? $data['jam_mulai'] : ($pertemuan->jam_mulai ?: now()->format('H:i')),
            'jam_selesai'        => array_key_exists('jam_selesai', $data) ? $data['jam_selesai'] : $pertemuan->jam_selesai,
            'presensi_closed_at' => null,
            'status_pertemuan'   => Pertemuan::STATUS_BERLANGSUNG,
        ]);

        $tokenData = $this->rotateTokenAbsensi($pertemuanId, $actorUserId);
        if (!empty($data['window_menit'])) {
            $pertemuan->update(['window_menit' => max(5, min(180, (int) $data['window_menit']))]);
            $tokenData['window_menit'] = (int) $pertemuan->window_menit;
        }

        return array_merge($tokenData, [
            'pertemuan' => $pertemuan,
        ]);
    }

    /**
     * Dosen menutup sesi presensi: input token mahasiswa ditolak setelah ini,
     * input manual dosen (bulk) tetap dimungkinkan untuk koreksi susulan.
     */
    public function tutupPresensi(int $pertemuanId, ?int $userId = null): Pertemuan
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        $pertemuan->update([
            'presensi_closed_at' => now(),
            'token_absensi'      => null,
            'token_expired_at'   => null,
            'status_pertemuan'   => 'selesai',
        ]);

        return $pertemuan->fresh();
    }

    /**
     * Mahasiswa menginputkan token absensi untuk mencatatkan kehadiran.
     * Syarat: sesi belum ditutup + token cocok (timing-safe, termasuk toleransi rotasi 10d) + belum kedaluwarsa
     * + mahasiswa terdaftar di kelas via KRS.
     */
    public function inputTokenAbsensi(int $pertemuanId, int $userId, string $token, ?string $catatan = null): AbsensiMahasiswa
    {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
        if (!$mahasiswa) {
            abort(403, 'Fitur presensi token mandiri hanya dapat digunakan oleh Mahasiswa.');
        }
        $mahasiswaId = $mahasiswa->id;

        $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
        app(PresensiPengajaranService::class)->assertWriteAllowed($pertemuan, $userId);

        if ($pertemuan->presensi_closed_at || $pertemuan->status_pertemuan === 'selesai') {
            throw ValidationException::withMessages([
                'token' => ['Sesi presensi sudah ditutup dosen.'],
            ]);
        }

        $cleanToken = trim($token);
        $currentMatches = !empty($pertemuan->token_absensi) && hash_equals((string) $pertemuan->token_absensi, $cleanToken);
        $prevToken = Cache::get("lms:presensi:prev_token:{$pertemuanId}");
        $prevMatches = is_string($prevToken) && $prevToken !== '' && hash_equals($prevToken, $cleanToken);

        if (!$currentMatches && !$prevMatches) {
            throw ValidationException::withMessages([
                'token' => ['Token absensi tidak cocok atau tidak valid.'],
            ]);
        }

        if ($currentMatches && (!$pertemuan->token_expired_at || now()->gt($pertemuan->token_expired_at))) {
            throw ValidationException::withMessages([
                'token' => ['Token absensi telah kedaluwarsa. Silakan scan ulang QR atau masukkan kode terbaru di layar dosen.'],
            ]);
        }

        // Mahasiswa wajib peserta kelas (KRS aktif/valid) — cegah presensi ke kelas lain.
        if (!$this->isMahasiswaEnrolledInKelas($mahasiswaId, (int) $pertemuan->kelas_id)) {
            throw ValidationException::withMessages([
                'token' => ['Anda tidak terdaftar pada kelas perkuliahan ini.'],
            ]);
        }

        return AbsensiMahasiswa::updateOrCreate(
            [
                'pertemuan_id' => $pertemuanId,
                'mahasiswa_id' => $mahasiswaId,
            ],
            [
                'status'       => 'hadir',
                'is_submitted' => true,
                'catatan'      => $catatan ?? 'Presensi mandiri via token LMS',
            ]
        );
    }

    /**
     * Mahasiswa melakukan scan QR Code atau mengirim payload scanner (Web / Mobile App).
     * Mendukung:
     * 1) `qr_payload` berformat `RAG-PRESENSI:{pertemuan_id}:{token}`
     * 2) `qr_payload` berformat JSON `{"pertemuan_id":123,"token":"654321"}`
     * 3) `pertemuan_id` + `token` (atau `qr_payload` berisi 6-digit token langsung saat `pertemuan_id` dikirim)
     */
    public function scanTokenAbsensi(int $userId, array $payload): array
    {
        $pertemuanId = isset($payload['pertemuan_id']) ? (int) $payload['pertemuan_id'] : null;
        $token = isset($payload['token']) ? trim((string) $payload['token']) : null;
        $qrPayload = isset($payload['qr_payload']) ? trim((string) $payload['qr_payload']) : '';

        if ($qrPayload !== '') {
            if (preg_match('/^RAG-PRESENSI:(\d+):([A-Za-z0-9]{6})$/i', $qrPayload, $matches)) {
                $pertemuanId = (int) $matches[1];
                $token = $matches[2];
            } elseif (str_starts_with($qrPayload, '{')) {
                $decoded = json_decode($qrPayload, true);
                if (is_array($decoded)) {
                    $pertemuanId = isset($decoded['pertemuan_id']) ? (int) $decoded['pertemuan_id'] : $pertemuanId;
                    $token = isset($decoded['token']) ? trim((string) $decoded['token']) : $token;
                }
            } elseif ($pertemuanId && preg_match('/^[A-Za-z0-9]{6}$/', $qrPayload)) {
                $token = $qrPayload;
            }
        }

        if (!$pertemuanId || !$token) {
            throw ValidationException::withMessages([
                'qr_payload' => ['Format QR Code atau token presensi tidak valid. Pastikan memindai QR Code presensi dari layar dosen.'],
            ]);
        }

        $catatan = 'Presensi mandiri via QR Scanner';
        if (!empty($payload['device_info'])) {
            $catatan .= ' (' . mb_substr(trim((string) $payload['device_info']), 0, 80) . ')';
        }

        $absensi = $this->inputTokenAbsensi($pertemuanId, $userId, $token, $catatan);
        $pertemuan = Pertemuan::with(['kelas.mataKuliah', 'kelas.dosenPengampu.dosen'])->findOrFail($pertemuanId);

        return [
            'pertemuan_id' => $pertemuan->id,
            'pertemuan_ke' => $pertemuan->pertemuan_ke,
            'tanggal'      => $pertemuan->tanggal,
            'materi'       => $pertemuan->materi,
            'kelas_id'     => $pertemuan->kelas_id,
            'kode_kelas'   => $pertemuan->kelas?->kode_kelas,
            'nama_kelas'   => $pertemuan->kelas?->nama_kelas,
            'mata_kuliah'  => $pertemuan->kelas?->mataKuliah?->nama,
            'status'       => $absensi->status,
            'waktu_absen'  => $absensi->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * Daftar sesi presensi kelas yang sedang aktif dibuka dosen untuk mahasiswa (periode aktif).
     */
    public function listSesiPresensiAktifMahasiswa(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
        if (!$mahasiswa) {
            return [];
        }

        $kelasIds = $this->enrolledKelasIdsForMahasiswa($mahasiswa->id);
        if (empty($kelasIds)) {
            return [];
        }

        $effectiveTahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->value('id');

        $pertemuans = Pertemuan::with(['kelas.mataKuliah', 'kelas.dosenPengampu.dosen', 'kelas.ruangan'])
            ->whereIn('kelas_id', $kelasIds)
            ->when($effectiveTahunAkademikId, function ($q) use ($effectiveTahunAkademikId) {
                $q->whereHas('kelas', fn ($k) => $k->where('tahun_akademik_id', $effectiveTahunAkademikId));
            })
            ->where('status_pertemuan', Pertemuan::STATUS_BERLANGSUNG)
            ->whereNull('presensi_closed_at')
            ->whereNotNull('token_absensi')
            ->orderByDesc('token_rotated_at')
            ->get();

        $absensiMap = AbsensiMahasiswa::whereIn('pertemuan_id', $pertemuans->pluck('id'))
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('is_submitted', true)
            ->get()
            ->keyBy('pertemuan_id');

        return $pertemuans->map(function (Pertemuan $p) use ($absensiMap) {
            $myAbsen = $absensiMap->get($p->id);
            return [
                'pertemuan_id'   => $p->id,
                'pertemuan_ke'   => $p->pertemuan_ke,
                'tanggal'        => $p->tanggal,
                'jam_mulai'      => $p->jam_mulai,
                'jam_selesai'    => $p->jam_selesai,
                'materi'         => $p->materi,
                'kelas_id'       => $p->kelas_id,
                'kode_kelas'     => $p->kelas?->kode_kelas,
                'nama_kelas'     => $p->kelas?->nama_kelas,
                'mata_kuliah'    => $p->kelas?->mataKuliah?->nama,
                'ruangan'        => $p->kelas?->ruangan?->nama_ruangan ?? null,
                'sudah_absen'    => $myAbsen !== null,
                'status_absensi' => $myAbsen?->status,
                'waktu_absen'    => $myAbsen?->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Input absensi massal oleh dosen untuk satu pertemuan.
     */
    public function bulkInputAbsensi(int $pertemuanId, array $absensiData, int $userId): void
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, $userId);
        app(PresensiPengajaranService::class)->assertWriteAllowed($pertemuan, $userId);

        $statusIds = collect($absensiData)->pluck('status_id')->unique()->values();
        $statusReferences = MasterReferensi::query()
            ->active()
            ->where('modul', 'siakad')
            ->where('tipe', 'status_absensi')
            ->whereIn('id', $statusIds)
            ->get()
            ->keyBy('id');

        $enrolledStudentIds = KrsDetail::query()
            ->where('kelas_id', $pertemuan->kelas_id)
            ->whereHas('krs', fn ($krs) => $krs->whereIn('status', self::VALID_KRS_STATUSES))
            ->with('krs.mahasiswa:id')
            ->get()
            ->map(fn (KrsDetail $detail) => $detail->krs?->mahasiswa?->id)
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidEntries = [];
        foreach ($absensiData as $index => $item) {
            $reference = $statusReferences->get($item['status_id'] ?? null);
            if (!$reference || !$reference->kode || mb_strlen($reference->kode) > 50) {
                $invalidEntries["absensi.{$index}.status_id"][] = 'Pilih status absensi SIAKAD yang aktif dengan kode maksimal 50 karakter.';
            }
            if (!in_array((int) ($item['mahasiswa_id'] ?? 0), $enrolledStudentIds, true)) {
                $invalidEntries["absensi.{$index}.mahasiswa_id"][] = 'Mahasiswa bukan peserta KRS yang valid untuk kelas pertemuan ini.';
            }
        }

        if ($invalidEntries !== []) {
            throw ValidationException::withMessages($invalidEntries);
        }

        DB::transaction(function () use ($pertemuanId, $absensiData, $statusReferences) {
            foreach ($absensiData as $item) {
                $statusKode = $statusReferences->get($item['status_id'])->kode;
                $attendance = AbsensiMahasiswa::firstOrNew(
                    [
                        'pertemuan_id' => $pertemuanId,
                        'mahasiswa_id' => $item['mahasiswa_id'],
                    ]
                );
                $trackedFields = ['status', 'is_submitted', 'catatan'];
                $oldValues = $attendance->exists
                    ? array_intersect_key($attendance->getOriginal(), array_flip($trackedFields))
                    : null;
                $attendance->fill([
                    'status' => $statusKode,
                    'is_submitted' => true,
                    'catatan' => $item['catatan'] ?? null,
                ])->save();

                if ($attendance->wasRecentlyCreated || $attendance->wasChanged($trackedFields)) {
                    try {
                        AuditLogService::record(
                            module: 'LMS',
                            action: $attendance->wasRecentlyCreated ? 'create' : 'update',
                            tableName: 'siakad_absensi_mahasiswa',
                            recordId: $attendance->id,
                            oldValues: $oldValues,
                            newValues: $attendance->only($trackedFields)
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Gagal mencatat audit log absensi LMS.', [
                            'exception' => $e,
                            'pertemuan_id' => $pertemuanId,
                            'absensi_id' => $attendance->id,
                        ]);
                    }
                }
            }
        });

    }

    /**
     * Mahasiswa mengajukan izin atau sakit untuk absensi pertemuan.
     */
    public function ajukanIzin(int $pertemuanId, int $userId, array $data, ?UploadedFile $suratFile = null): IzinAbsensi
    {
        return DB::transaction(function () use ($pertemuanId, $userId, $data, $suratFile) {
            $pertemuan = Pertemuan::with('kelas')->findOrFail($pertemuanId);
            $mahasiswa = $this->assertMahasiswaPesertaKelas((int) $pertemuan->kelas_id, $userId);
            $mahasiswaId = $mahasiswa->id;

            $disk = $this->resolveDisk($pertemuan->kelas);

            $suratPath = null;
            if ($suratFile) {
                $suratPath = $this->storeUploadedFile($suratFile, 'siakad/lms/izin', $disk);
            }

            $tipeIzinId = $data['tipe_izin_id'] ?? null;
            $tipeIzin   = $data['tipe_izin'] ?? null;
            if ($tipeIzinId && !$tipeIzin) {
                $ref = DB::table('spmb_master_referensi')->find($tipeIzinId);
                $tipeIzin = $ref?->kode ?? 'izin';
            }

            return IzinAbsensi::updateOrCreate(
                [
                    'pertemuan_id' => $pertemuanId,
                    'mahasiswa_id' => $mahasiswaId,
                ],
                [
                    'tipe_izin_id' => $tipeIzinId,
                    'tipe_izin'    => $tipeIzin ?? 'izin',
                    'alasan'       => $data['alasan'],
                    'surat_path'   => $suratPath,
                    'disk'         => $disk,
                    'status'       => 'pending',
                ]
            );
        });
    }

    /**
     * Dosen memproses (setujui / tolak) pengajuan izin absensi mahasiswa.
     */
    public function prosesIzin(int $izinId, int $statusId, ?string $catatan, int $dosenUserId): IzinAbsensi
    {
        $izinUntukDiproses = IzinAbsensi::with('pertemuan')->findOrFail($izinId);
        if ($izinUntukDiproses->pertemuan) {
            $this->assertDosenPengampuKelas((int) $izinUntukDiproses->pertemuan->kelas_id, $dosenUserId);
            app(PresensiPengajaranService::class)->assertWriteAllowed($izinUntukDiproses->pertemuan, $dosenUserId);
        }

        return DB::transaction(function () use ($izinId, $statusId, $catatan, $dosenUserId) {
            $izin = IzinAbsensi::findOrFail($izinId);

            $ref = DB::table('spmb_master_referensi')->find($statusId);
            $statusKode = $ref?->kode ?? 'disetujui';

            $izin->update([
                'status'        => $statusKode,
                'catatan_dosen' => $catatan,
                'diproses_oleh' => $dosenUserId,
                'diproses_at'   => now(),
            ]);

            // Jika disetujui, update record siakad_absensi_mahasiswa ke status sakit/izin
            if ($statusKode === 'disetujui') {
                AbsensiMahasiswa::updateOrCreate(
                    [
                        'pertemuan_id' => $izin->pertemuan_id,
                        'mahasiswa_id' => $izin->mahasiswa_id,
                    ],
                    [
                        'status'  => $izin->tipe_izin === 'sakit' ? 'sakit' : 'izin',
                        'is_submitted' => true,
                        'catatan' => 'Disetujui dari pengajuan surat izin: ' . ($catatan ?? '-'),
                    ]
                );
            }

            return $izin->fresh();
        });
    }

    /**
     * Rekapitulasi kehadiran satu kelas lengkap untuk seluruh pertemuan.
     *
     * @param int|null $onlyMahasiswaId Bila diisi, hanya baris mahasiswa tersebut
     *                                  yang dikembalikan (privasi data mahasiswa).
     */
    public function getRekapAbsensiKelas(int $kelasId, ?int $onlyMahasiswaId = null): array
    {
        $kelas = Kelas::with(['lmsSetting', 'pertemuans'])->findOrFail($kelasId);
        $totalPertemuan = $kelas->pertemuans->count();

        // Ambil seluruh mahasiswa yang mengambil kelas ini via KRS Detail
        $krsDetails = KrsDetail::with('krs.mahasiswa')
            ->where('kelas_id', $kelasId)
            ->whereHas('krs', fn ($krs) => $krs->whereIn('status', ['disetujui', 'diajukan']))
            ->get();
        $krsDetails = $krsDetails
            ->unique(fn (KrsDetail $detail) => $detail->krs?->mahasiswa_id)
            ->values();

        $minHadirPersen = $kelas->lmsSetting?->batas_min_hadir_persen ?? 75;

        $rekap = [];
        foreach ($krsDetails as $kd) {
            $mhs = $kd->krs?->mahasiswa;
            if (!$mhs) continue;
            if ($onlyMahasiswaId && (int) $mhs->id !== (int) $onlyMahasiswaId) continue;

            $absensiList = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
                ->where('mahasiswa_id', $mhs->id)
                ->where('is_submitted', true)
                ->get();

            $hadir = $absensiList->where('status', 'hadir')->count();
            $sakit = $absensiList->where('status', 'sakit')->count();
            $izin  = $absensiList->where('status', 'izin')->count();
            $alfa  = $absensiList->where('status', 'alfa')->count();

            $persenHadir = $totalPertemuan > 0 ? round(($hadir / $totalPertemuan) * 100, 1) : 0;
            $memenuhiSyarat = $persenHadir >= $minHadirPersen;

            $rekap[] = [
                'mahasiswa_id'     => $mhs->id,
                'nim'              => $mhs->nim,
                'nama_lengkap'     => $mhs->nama_lengkap,
                'hadir'            => $hadir,
                'sakit'            => $sakit,
                'izin'             => $izin,
                'alfa'             => $alfa,
                'total_pertemuan'  => $totalPertemuan,
                'persen_kehadiran' => $persenHadir,
                'memenuhi_syarat'  => $memenuhiSyarat,
            ];
        }

        return [
            'kelas_id'               => $kelas->id,
            'total_pertemuan'        => $totalPertemuan,
            'batas_min_hadir_persen' => $minHadirPersen,
            'rekapitulasi'           => $rekap,
        ];
    }

    /**
     * Dapatkan daftar kelas yang diampu dosen atau diambil mahasiswa dengan pagination dan filter.
     */
    public function getMyKelas(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        ?int $tahunAkademikId = null,
        ?int $programStudiId = null,
        ?int $minKapasitas = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Kelas::with(['mataKuliah', 'tahunAkademik', 'programStudi', 'dosenPengampu.dosen', 'ruangan']);

        // Tanpa filter eksplisit, batasi ke periode aktif agar tidak tercampur semua tahun akademik.
        $tahunAkademikId ??= TahunAkademik::where('is_active', true)->value('id');
        $effectiveTahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->value('id');
        if ($effectiveTahunAkademikId) {
            $query->where('tahun_akademik_id', $effectiveTahunAkademikId);
        }
        if ($programStudiId) {
            $query->where(function ($program) use ($programStudiId) {
                $program->where('program_studi_id', $programStudiId)
                    ->orWhereHas('programStudis', fn ($query) => $query->whereKey($programStudiId));
            });
        }
        if ($minKapasitas !== null) {
            $query->where('kapasitas', '>=', $minKapasitas);
        }

        $this->applyKelasScope($query, $userId, 'id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                  ->orWhere('kode_kelas', 'like', "%{$search}%")
                  ->orWhereHas('mataKuliah', function ($mk) use ($search) {
                      $mk->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_mk', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Daftar id kelas yang boleh diakses user.
     *
     * - Dosen    -> kelas yang diampu (DosenPengampu).
     * - Mahasiswa-> kelas yang diambil lewat KRS.
     * - Admin/Superadmin/Kaprodi/Wakil Prodi -> null (berarti SEMUA kelas).
     * - User tanpa relasi dosen/mahasiswa & bukan privileged -> array kosong.
     *
     * Dipakai bersama oleh seluruh endpoint agregat module-level LMS
     * (pertemuan/tryout/forum/pengaturan) agar scoping konsisten dengan getMyKelas().
     *
     * @return array<int>|null
     */
    /**
     * Ringkasan dashboard LMS (split role, cache 5 menit).
     *
     * Statistik dasar memakai scoping kelas yang sama dengan endpoint LMS lain
     * (`resolveAccessibleKelasIds`): dosen → kelas yang diampu, mahasiswa →
     * kelas dari KRS, pengelola (admin/kaprodi/wakil prodi) → seluruh kelas.
     * Role ditentukan dari profil yang dimiliki user, bukan atribut statis.
     *
     * @return array{role: string, ringkasan: array<string, int>, tambahan: array<string, int>, tren: array<int, array<string, mixed>>}
     */
    public function dashboardOverview(int $userId, ?int $tahunAkademikId = null): array
    {
        $effectiveTahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->value('id');
        $today = now();
        $cacheKey = "lms:dashboard:overview:{$userId}:" . ($effectiveTahunAkademikId ?: 'all') . ':' . $today->toDateString();

        return Cache::remember($cacheKey, 300, function () use ($userId, $effectiveTahunAkademikId) {
            $kelasIds = $this->resolveAccessibleKelasIds($userId);
            $user = User::find($userId);
            $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
            $dosen = Dosen::where('user_id', $userId)->first();

            $isPengelolaRole = $this->isGlobalLmsManager($user, $dosen, $mahasiswa)
                || ($dosen !== null && $this->resolveManagedProdiIds($user, $dosen) !== []);

            $role = $mahasiswa
                ? 'mahasiswa'
                : (($dosen && !$isPengelolaRole) ? 'dosen' : 'pengelola');

            $hariIni = [
                'minggu',
                'senin',
                'selasa',
                'rabu',
                'kamis',
                'jumat',
                'sabtu',
            ][now()->dayOfWeek];
            $kelasDashboard = [
                'hari_ini' => [],
                'semua' => [],
            ];

            if (in_array($role, ['mahasiswa', 'dosen'], true)) {
                $kelasItems = Kelas::query()
                    ->with('mataKuliah')
                    ->whereIn('id', $kelasIds ?? [])
                    ->when(
                        $effectiveTahunAkademikId,
                        fn ($query) => $query->where('tahun_akademik_id', $effectiveTahunAkademikId)
                    )
                    ->get()
                    ->map(function (Kelas $kelas) {
                        $mataKuliah = $kelas->mataKuliah;

                        return [
                            'id' => $kelas->id,
                            'kode_kelas' => $kelas->kode_kelas,
                            'nama_kelas' => $kelas->nama_kelas,
                            'hari' => $kelas->hari ? mb_strtolower($kelas->hari) : null,
                            'jam_mulai' => $kelas->jam_mulai,
                            'jam_selesai' => $kelas->jam_selesai,
                            'mata_kuliah' => $mataKuliah ? [
                                'id' => $mataKuliah->id,
                                'kode' => $mataKuliah->kode_mk,
                                'nama' => $mataKuliah->nama,
                            ] : null,
                        ];
                    })
                    ->sortBy(function (array $kelas) {
                        $urutanHari = [
                            'senin' => 1,
                            'selasa' => 2,
                            'rabu' => 3,
                            'kamis' => 4,
                            'jumat' => 5,
                            'sabtu' => 6,
                            'minggu' => 7,
                        ];

                        return sprintf(
                            '%d-%s-%s',
                            $urutanHari[$kelas['hari'] ?? ''] ?? 8,
                            $kelas['jam_mulai'] ?? '99:99:99',
                            $kelas['kode_kelas'] ?? ''
                        );
                    })
                    ->values();

                $kelasDashboard = [
                    'hari_ini' => $kelasItems
                        ->where('hari', $hariIni)
                        ->values()
                        ->all(),
                    'semua' => $kelasItems->all(),
                ];
            }

            $kelasTotal = Kelas::query()
                ->when($kelasIds !== null, fn ($query) => $query->whereIn('id', $kelasIds))
                ->when($effectiveTahunAkademikId, fn ($query) => $query->where('tahun_akademik_id', $effectiveTahunAkademikId))
                ->count();

            $tugasAktif = Tugas::query()
                ->where('is_published', true)
                ->where('deadline_at', '>=', now())
                ->when($kelasIds !== null, function ($query) use ($kelasIds) {
                    $query->whereHas('pertemuan', fn ($pertemuan) => $pertemuan->whereIn('kelas_id', $kelasIds));
                })
                ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                    $query->whereHas('pertemuan.kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                })
                ->count();

            $tryoutAktif = Quiz::query()
                ->where('tipe', Quiz::TIPE_TRYOUT)
                ->where('is_published', true)
                ->where('is_archived', false)
                ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                    $query->where(function ($q) use ($effectiveTahunAkademikId) {
                        $q->whereNull('kelas_id')
                            ->orWhereHas('kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                    });
                })
                ->when($kelasIds !== null, function ($query) use ($kelasIds, $userId, $dosen, $mahasiswa) {
                    $query->where(function ($q) use ($kelasIds, $userId, $dosen, $mahasiswa) {
                        if (!empty($kelasIds)) {
                            $q->whereIn('kelas_id', $kelasIds);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                        if ($mahasiswa) {
                            $q->orWhereHas('tryoutPeserta', fn ($tp) => $tp->where('mahasiswa_id', $mahasiswa->id));
                        } else {
                            $q->orWhere(fn ($sub) => $sub->whereNull('kelas_id')->where('dibuat_oleh', $userId));
                            if ($dosen) {
                                $q->orWhereHas('kolaborators', fn ($k) => $k->where('dosen_id', $dosen->id));
                            }
                        }
                    });
                })
                ->count();

            $forumTopik = ForumTopik::query()
                ->when($kelasIds !== null, fn ($query) => $query->whereIn('kelas_id', $kelasIds))
                ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                    $query->whereHas('kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                })
                ->count();

            $ringkasan = [
                'kelas' => $kelasTotal,
                'tugas_aktif' => $tugasAktif,
                'tryout_aktif' => $tryoutAktif,
                'forum_topik' => $forumTopik,
                'notifikasi_belum_dibaca' => LmsNotifikasi::query()
                    ->where('user_id', $userId)
                    ->where('is_read', false)
                    ->count(),
            ];

            $tambahan = [];

            if ($mahasiswa) {
                $tambahan['tugas_belum_dikumpul'] = Tugas::query()
                    ->where('is_published', true)
                    ->when($kelasIds !== null, function ($query) use ($kelasIds) {
                        $query->whereHas('pertemuan', fn ($pertemuan) => $pertemuan->whereIn('kelas_id', $kelasIds));
                    })
                    ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                        $query->whereHas('pertemuan.kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                    })
                    ->whereDoesntHave('pengumpulan', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
                    ->count();

                $tambahan['tryout_selesai'] = QuizAttempt::query()
                    ->where('mahasiswa_id', $mahasiswa->id)
                    ->where('status', 'selesai')
                    ->whereHas('quiz', fn ($query) => $query->where('is_published', true)->where('is_archived', false))
                    ->count();
            } else {
                $tambahan['pengumpulan_belum_dinilai'] = PengumpulanTugas::query()
                    ->whereNull('nilai')
                    ->when($kelasIds !== null, function ($query) use ($kelasIds) {
                        $query->whereHas('tugas.pertemuan', fn ($pertemuan) => $pertemuan->whereIn('kelas_id', $kelasIds));
                    })
                    ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                        $query->whereHas('tugas.pertemuan.kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                    })
                    ->count();

                $tambahan['attempt_belum_dinilai'] = QuizAttempt::query()
                    ->where('butuh_penilaian_manual', true)
                    ->when($kelasIds !== null, function ($query) use ($kelasIds, $userId, $dosen) {
                        $query->whereHas('quiz', function ($quiz) use ($kelasIds, $userId, $dosen) {
                            $quiz->where(function ($q) use ($kelasIds, $userId, $dosen) {
                                if (!empty($kelasIds)) {
                                    $q->whereIn('kelas_id', $kelasIds);
                                } else {
                                    $q->whereRaw('1 = 0');
                                }
                                $q->orWhere(fn ($sub) => $sub->whereNull('kelas_id')->where('dibuat_oleh', $userId));
                                if ($dosen) {
                                    $q->orWhereHas('kolaborators', fn ($k) => $k->where('dosen_id', $dosen->id));
                                }
                            });
                        });
                    })
                    ->count();

                $tambahan['kelas_belum_dikonfigurasi'] = Kelas::query()
                    ->whereDoesntHave('lmsSetting')
                    ->when($kelasIds !== null, fn ($query) => $query->whereIn('id', $kelasIds))
                    ->when($effectiveTahunAkademikId, fn ($query) => $query->where('tahun_akademik_id', $effectiveTahunAkademikId))
                    ->count();
            }

            $start = now()->subDays(6)->startOfDay();

            $pengumpulanQuery = PengumpulanTugas::query()->where('created_at', '>=', $start);
            if ($mahasiswa) {
                $pengumpulanQuery->where('mahasiswa_id', $mahasiswa->id);
            } elseif ($kelasIds !== null) {
                $pengumpulanQuery->whereHas('tugas.pertemuan', fn ($pertemuan) => $pertemuan->whereIn('kelas_id', $kelasIds));
            }

            $attemptQuery = QuizAttempt::query()->where('created_at', '>=', $start);
            if ($mahasiswa) {
                $attemptQuery->where('mahasiswa_id', $mahasiswa->id);
            } elseif ($kelasIds !== null) {
                $attemptQuery->whereHas('quiz', fn ($quiz) => $quiz->whereIn('kelas_id', $kelasIds));
            }

            $pengumpulanPerHari = $pengumpulanQuery->pluck('created_at')
                ->groupBy(fn ($date) => $date->format('Y-m-d'))
                ->map->count();

            $attemptPerHari = $attemptQuery->pluck('created_at')
                ->groupBy(fn ($date) => $date->format('Y-m-d'))
                ->map->count();

            $tren = collect(range(0, 6))
                ->map(function (int $offset) use ($start, $pengumpulanPerHari, $attemptPerHari) {
                    $tanggal = $start->copy()->addDays($offset)->format('Y-m-d');

                    return [
                        'tanggal' => $tanggal,
                        'pengumpulan' => (int) ($pengumpulanPerHari[$tanggal] ?? 0),
                        'attempt' => (int) ($attemptPerHari[$tanggal] ?? 0),
                    ];
                })
                ->all();

            $preview = [
                'tugas' => [],
                'kuis' => [],
                'materi' => [],
            ];

            if (in_array($role, ['mahasiswa', 'dosen'], true)) {
                $tugasPreviewQuery = Tugas::query()
                    ->where('is_published', true)
                    ->whereHas('pertemuan.kelas', function ($kelas) use ($kelasIds, $effectiveTahunAkademikId) {
                        if ($kelasIds !== null) {
                            $kelas->whereIn('id', $kelasIds);
                        }
                        if ($effectiveTahunAkademikId) {
                            $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId);
                        }
                    })
                    ->where(function ($query) {
                        $query->whereNull('deadline_at')
                            ->orWhere('deadline_at', '>=', now())
                            ->orWhere('can_submit_late', true);
                    })
                    ->with('pertemuan.kelas.mataKuliah')
                    ->withCount([
                        'pengumpulan as belum_dinilai_count' => fn ($query) => $query->whereNull('nilai'),
                    ]);

                if ($mahasiswa) {
                    $tugasPreviewQuery->whereDoesntHave(
                        'pengumpulan',
                        fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id)
                    );
                }

                $preview['tugas'] = $tugasPreviewQuery
                    ->orderByRaw('deadline_at IS NULL')
                    ->orderBy('deadline_at')
                    ->orderByDesc('updated_at')
                    ->limit(5)
                    ->get()
                    ->map(function (Tugas $tugas) use ($mahasiswa) {
                        $pertemuan = $tugas->pertemuan;
                        $kelas = $pertemuan?->kelas;
                        $mataKuliah = $kelas?->mataKuliah;

                        return [
                            'id' => $tugas->id,
                            'judul' => $tugas->judul,
                            'deadline_at' => $tugas->deadline_at?->toISOString(),
                            'pertemuan_id' => $pertemuan?->id,
                            'kelas_id' => $kelas?->id,
                            'kode_kelas' => $kelas?->kode_kelas,
                            'nama_kelas' => $kelas?->nama_kelas,
                            'mata_kuliah' => $mataKuliah ? [
                                'id' => $mataKuliah->id,
                                'kode' => $mataKuliah->kode_mk,
                                'nama' => $mataKuliah->nama,
                            ] : null,
                            'belum_dinilai' => $mahasiswa ? null : (int) $tugas->belum_dinilai_count,
                        ];
                    })
                    ->all();

                $quizPreviewQuery = Quiz::query()
                    ->where('is_published', true)
                    ->where('is_archived', false)
                    ->where(function ($query) use ($kelasIds) {
                        $query->whereHas('kelas', function ($kelas) use ($kelasIds) {
                            if ($kelasIds !== null) {
                                $kelas->whereIn('id', $kelasIds);
                            }
                        })->orWhereHas('pertemuan.kelas', function ($kelas) use ($kelasIds) {
                            if ($kelasIds !== null) {
                                $kelas->whereIn('id', $kelasIds);
                            }
                        });
                    })
                    ->where(function ($query) {
                        $query->whereNull('ditutup_at')
                            ->orWhere('ditutup_at', '>=', now());
                    })
                    ->when($effectiveTahunAkademikId, function ($query) use ($effectiveTahunAkademikId) {
                        $query->where(function ($scope) use ($effectiveTahunAkademikId) {
                            $scope->whereHas('kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId))
                                ->orWhereHas('pertemuan.kelas', fn ($kelas) => $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId));
                        });
                    })
                    ->with(['kelas.mataKuliah', 'pertemuan.kelas.mataKuliah'])
                    ->orderByRaw('ditutup_at IS NULL')
                    ->orderBy('ditutup_at')
                    ->orderByDesc('id')
                    ->limit(5);

                $preview['kuis'] = $quizPreviewQuery
                    ->get()
                    ->map(function (Quiz $quiz) {
                        $pertemuan = $quiz->pertemuan;
                        $kelas = $quiz->kelas ?? $pertemuan?->kelas;
                        $mataKuliah = $kelas?->mataKuliah;

                        return [
                            'id' => $quiz->id,
                            'judul' => $quiz->judul,
                            'tipe' => $quiz->tipe,
                            'dibuka_at' => $quiz->dibuka_at?->toISOString(),
                            'deadline_at' => $quiz->ditutup_at?->toISOString(),
                            'pertemuan_id' => $pertemuan?->id,
                            'kelas_id' => $kelas?->id,
                            'kode_kelas' => $kelas?->kode_kelas,
                            'nama_kelas' => $kelas?->nama_kelas,
                            'mata_kuliah' => $mataKuliah ? [
                                'id' => $mataKuliah->id,
                                'kode' => $mataKuliah->kode_mk,
                                'nama' => $mataKuliah->nama,
                            ] : null,
                        ];
                    })
                    ->all();

                $preview['materi'] = MateriPertemuan::query()
                    ->where('is_published', true)
                    ->whereHas('pertemuan.kelas', function ($kelas) use ($kelasIds, $effectiveTahunAkademikId) {
                        if ($kelasIds !== null) {
                            $kelas->whereIn('id', $kelasIds);
                        }
                        if ($effectiveTahunAkademikId) {
                            $kelas->where('tahun_akademik_id', $effectiveTahunAkademikId);
                        }
                    })
                    ->with('pertemuan.kelas.mataKuliah')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get()
                    ->map(function (MateriPertemuan $materi) {
                        $pertemuan = $materi->pertemuan;
                        $kelas = $pertemuan?->kelas;
                        $mataKuliah = $kelas?->mataKuliah;

                        return [
                            'id' => $materi->id,
                            'judul' => $materi->judul,
                            'updated_at' => $materi->updated_at?->toISOString(),
                            'pertemuan_id' => $pertemuan?->id,
                            'kelas_id' => $kelas?->id,
                            'kode_kelas' => $kelas?->kode_kelas,
                            'nama_kelas' => $kelas?->nama_kelas,
                            'mata_kuliah' => $mataKuliah ? [
                                'id' => $mataKuliah->id,
                                'kode' => $mataKuliah->kode_mk,
                                'nama' => $mataKuliah->nama,
                            ] : null,
                        ];
                    })
                    ->all();
            }

            return [
                'role' => $role,
                'tahun_akademik_id' => $effectiveTahunAkademikId ? (int) $effectiveTahunAkademikId : null,
                'jadwal' => [
                    'hari_ini' => $hariIni,
                    'tanggal' => now()->toDateString(),
                    'kelas' => $kelasDashboard,
                ],
                'ringkasan' => $ringkasan,
                'tambahan' => $tambahan,
                'tren' => $tren,
                'preview' => $preview,
            ];
        });
    }

    public function resolveAccessibleKelasIds(int $userId): ?array
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        $dosen = Dosen::where('user_id', $userId)->first();
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();

        if ($this->isGlobalLmsManager($user, $dosen, $mahasiswa)) {
            return null;
        }

        if ($dosen) {
            $managedProdiIds = $this->resolveManagedProdiIds($user, $dosen);
            $prodiKelasIds = $managedProdiIds === []
                ? []
                : Kelas::query()
                    ->where(function ($q) use ($managedProdiIds) {
                        $q->whereIn('program_studi_id', $managedProdiIds)
                            ->orWhereHas('programStudis', fn ($ps) => $ps->whereIn('siakad_program_studi.id', $managedProdiIds));
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

            $pengampuKelasIds = DosenPengampu::where('dosen_id', $dosen->id)
                ->pluck('kelas_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return array_values(array_unique(array_merge($prodiKelasIds, $pengampuKelasIds)));
        }

        if ($mahasiswa) {
            return $this->enrolledKelasIdsForMahasiswa($mahasiswa->id);
        }

        return [];
    }

    /**
     * Terapkan scoping akses kelas ke sebuah query yang sudah punya relasi `kelas`.
     * $kelasIds null = semua kelas (privileged), array kosong = tidak ada akses.
     */
    private function applyKelasScope($query, int $userId, string $kelasRelation = 'kelas_id'): void
    {
        $kelasIds = $this->resolveAccessibleKelasIds($userId);

        if ($kelasIds === null) {
            return;
        }

        if ($kelasIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($kelasRelation, $kelasIds);
    }

    /**
     * Daftar pertemuan dari SELURUH kelas yang bisa diakses user (agregat module-level).
     *
     * Dipakai halaman /lms/pertemuan. Endpoint per-kelas
     * (/siakad/perkuliahan/kelas/{kelasId}/pertemuan) tetap tersedia untuk
     * kebutuhan di dalam konteks satu kelas.
     */
    public function listPertemuanSaya(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'tanggal',
        string $sortOrder = 'desc',
        ?int $tahunAkademikId = null,
        ?string $statusPertemuan = null,
        ?int $kelasId = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Pertemuan::with(['kelas.mataKuliah', 'kelas.tahunAkademik', 'kelas.programStudi', 'kelas.dosenPengampu.dosen']);

        $this->applyKelasScope($query, $userId);

        // Filter kelas selalu digabung (AND) dengan applyKelasScope di atas, jadi
        // kelas di luar hak user tidak bisa dimunculkan hanya lewat kelas_id.
        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        $effectiveTahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->value('id');
        if ($effectiveTahunAkademikId && !$kelasId) {
            $query->whereHas('kelas', function ($q) use ($effectiveTahunAkademikId) {
                $q->where('tahun_akademik_id', $effectiveTahunAkademikId);
            });
        } elseif ($tahunAkademikId && $kelasId) {
            $query->whereHas('kelas', function ($q) use ($tahunAkademikId) {
                $q->where('tahun_akademik_id', $tahunAkademikId);
            });
        }

        if ($statusPertemuan) {
            $query->where('status_pertemuan', $statusPertemuan);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('materi', 'like', "%{$search}%")
                  ->orWhere('catatan_pertemuan', 'like', "%{$search}%")
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

        $allowedSorts = ['id', 'tanggal', 'pertemuan_ke', 'jam_mulai', 'status_pertemuan', 'created_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'tanggal';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Rekap pengaturan LMS yang bisa diakses user (agregat module-level).
     *
     * Mengembalikan setting per kelas yang sudah ada, dilengkapi kelas yang
     * belum punya setting (agar UI bisa menampilkan "Belum dikonfigurasi").
     */
    public function indexPengaturan(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'nama_kelas',
        string $sortOrder = 'asc',
        ?int $tahunAkademikId = null,
        ?string $metodeAbsensi = null,
        ?string $statusKonfigurasi = null
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = Kelas::with(['mataKuliah', 'tahunAkademik', 'programStudi', 'lmsSetting'])
            ->withCount([
                'pertemuans as pertemuan_count',
                'krsDetails as mahasiswa_count',
            ]);

        // Query ini berakar di tabel siakad_kelas, jadi kolomnya primary key 'id'.
        $this->applyKelasScope($query, $userId, 'id');

        $effectiveTahunAkademikId = $tahunAkademikId ?? TahunAkademik::where('is_active', true)->value('id');
        if ($effectiveTahunAkademikId) {
            $query->where('tahun_akademik_id', $effectiveTahunAkademikId);
        }

        if ($metodeAbsensi) {
            // Filter menyaring nilai EFEKTIF: kelas tanpa baris setting memakai
            // nilai bawaan (KelasLmsSetting::METODE_KEDUANYA), jadi kelas
            // belum terkonfigurasi ikut muncul saat yang dicari adalah nilai bawaan.
            $query->where(function ($q) use ($metodeAbsensi) {
                $q->whereHas('lmsSetting', function ($setting) use ($metodeAbsensi) {
                    $setting->where('metode_absensi', $metodeAbsensi);
                });

                if ($metodeAbsensi === KelasLmsSetting::METODE_KEDUANYA) {
                    $q->orWhereDoesntHave('lmsSetting');
                }
            });
        }

        if ($statusKonfigurasi === self::STATUS_PENGATURAN_BELUM) {
            $query->whereDoesntHave('lmsSetting');
        } elseif ($statusKonfigurasi === self::STATUS_PENGATURAN_SUDAH) {
            $query->whereHas('lmsSetting');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                  ->orWhere('kode_kelas', 'like', "%{$search}%")
                  ->orWhereHas('mataKuliah', function ($mk) use ($search) {
                      $mk->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_mk', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSorts = ['id', 'nama_kelas', 'kode_kelas', 'created_at', 'updated_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'nama_kelas';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Ubah data pertemuan.
     *
     * Nomor pertemuan uniqueness dijaga terhadap pertemuan lain di kelas yang sama.
     */
    public function updatePertemuan(Pertemuan $pertemuan, array $data, ?int $userId = null): Pertemuan
    {
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        return DB::transaction(function () use ($pertemuan, $data) {
            $kelasId = $pertemuan->kelas_id;
            $pertemuanKe = $data['pertemuan_ke'] ?? $pertemuan->pertemuan_ke;

            $bentrok = Pertemuan::where('kelas_id', $kelasId)
                ->where('pertemuan_ke', $pertemuanKe)
                ->where('id', '!=', $pertemuan->id)
                ->exists();

            if ($bentrok) {
                throw ValidationException::withMessages([
                    'pertemuan_ke' => "Pertemuan ke-{$pertemuanKe} sudah ada pada kelas ini.",
                ]);
            }

            $pertemuan->fill($data);
            $pertemuan->save();

            // `load()` bukan `fresh()`: instance yang sama perlu dipertahankan agar
            // `getChanges()` tetap berisi daftar atribut yang benar-benar berubah,
            // dipakai controller untuk `$newValues` pada audit log.
            $pertemuan->load(['kelas.mataKuliah', 'kelas.tahunAkademik']);

            return $pertemuan;
        });
    }

    /**
     * Hapus pertemuan.
     *
     * Diblokir (422) bila sudah ada data turunan yang akan ikut hilang atau
     * membuat data absensi/penilaian jadi tidak konsisten: materi, tugas,
     * quiz, izin absensi, dan rekap absensi mahasiswa.
     */
    public function destroyPertemuan(Pertemuan $pertemuan, ?int $userId = null): void
    {
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertDosenPengampuKelas((int) $pertemuan->kelas_id, (int) $actorUserId);
        }

        $penghalang = [];

        if ($pertemuan->materiList()->exists()) {
            $penghalang[] = 'materi pertemuan';
        }
        if ($pertemuan->tugasList()->exists()) {
            $penghalang[] = 'tugas';
        }
        if ($pertemuan->quizList()->exists()) {
            $penghalang[] = 'quiz';
        }
        if ($pertemuan->izinAbsensiList()->exists()) {
            $penghalang[] = 'pengajuan izin absensi';
        }
        if ($pertemuan->absensi()->exists()) {
            $penghalang[] = 'rekap absensi mahasiswa';
        }

        if ($penghalang) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Pertemuan tidak dapat dihapus karena sudah memiliki: '
                    . implode(', ', $penghalang) . '. Hapus atau pindahkan datanya terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($pertemuan) {
            $pertemuan->delete();
        });
    }

    /**
     * Dapatkan daftar seluruh tugas milik mahasiswa yang sedang login dengan pagination.
     */
    public function getMyAllTugas(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
        if (!$mahasiswa) {
            return Tugas::whereRaw('1 = 0')->paginate($perPage);
        }

        $kelasIds = $this->enrolledKelasIdsForMahasiswa($mahasiswa->id);

        $query = Tugas::whereHas('pertemuan', function ($q) use ($kelasIds) {
            $q->whereIn('kelas_id', $kelasIds);
        })
        ->where('is_published', true)
        ->with(['pertemuan.kelas.mataKuliah', 'komponenPenilaian', 'pengumpulan' => function ($q) use ($mahasiswa) {
            $q->where('mahasiswa_id', $mahasiswa->id);
        }]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'judul', 'deadline_at', 'created_at', 'updated_at'];
        $sortField = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    /**
     * Dapatkan URL unduh aman untuk materi atau pengumpulan tugas.
     *
     * URL dihasilkan oleh `FileStorageService::temporaryUrl()` sehingga berkas
     * yang tersimpan pada disk privat otomatis memakai Signed URL, bukan URL
     * storage langsung.
     */
    public function getDownloadUrl(string $type, int $id, int $userId): array
    {
        if ($type === 'materi') {
            $file = MateriFile::with('materi.pertemuan')->findOrFail($id);
            $kelasId = $file->materi?->pertemuan?->kelas_id;
            if ($kelasId) {
                $context = $this->assertCanViewKelas((int) $kelasId, $userId);
                if ($context['viewer_mode'] === 'mahasiswa' && $file->materi && !$file->materi->is_published) {
                    abort(403, 'Materi pembelajaran ini belum dipublikasikan.');
                }
            }
            $disk = $file->disk;
            $path = $file->file_path;
            $filename = $file->nama_file;
        } elseif ($type === 'pengumpulan') {
            $pengumpulan = PengumpulanTugas::with('tugas.pertemuan')->findOrFail($id);
            $kelasId = $pengumpulan->tugas?->pertemuan?->kelas_id;
            if ($kelasId) {
                $context = $this->assertCanViewKelas((int) $kelasId, $userId);
                if ($context['viewer_mode'] === 'mahasiswa') {
                    $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
                    if (!$mahasiswa || (int) $pengumpulan->mahasiswa_id !== (int) $mahasiswa->id) {
                        abort(403, 'Anda hanya dapat mengunduh berkas pengumpulan tugas milik sendiri.');
                    }
                }
            }
            $disk = $pengumpulan->disk;
            $path = $pengumpulan->file_path;
            $filename = $pengumpulan->nama_file_asli ?: 'submission.pdf';
        } else {
            throw ValidationException::withMessages([
                'type' => ['Tipe download tidak dikenal.'],
            ]);
        }

        if (!$path || !$this->files->exists($path, $disk)) {
            throw new ModelNotFoundException('Berkas tidak ditemukan pada storage server.');
        }

        $url = $this->files->temporaryUrl($path, now()->addMinutes(30), $disk);

        if (!$url) {
            throw new ModelNotFoundException('URL unduh tidak dapat disiapkan untuk berkas ini.');
        }

        return [
            'nama_file'    => $filename,
            'download_url' => $url,
            'disk'         => $disk,
        ];
    }

    /**
     * Ambil pengaturan LMS satu kelas (read-only).
     *
     * Bila kelas belum pernah dikonfigurasi, mengembalikan setting bawaan
     * supaya frontend tidak perlu menangani kasus null.
     *
     * Akses dibatasi pada kelas yang boleh diakses user (KRS untuk mahasiswa,
     * kelas yang diampu untuk dosen, seluruh kelas untuk role privileged).
     */
    public function getKelasSetting(int $kelasId, int $userId): array
    {
        $kelas = Kelas::with('lmsSetting')->findOrFail($kelasId);

        $kelasIds = $this->resolveAccessibleKelasIds($userId);
        if ($kelasIds !== null && !in_array($kelas->id, $kelasIds, true)) {
            throw ValidationException::withMessages([
                'kelas' => ['Anda tidak terdaftar pada kelas perkuliahan ini.'],
            ]);
        }

        $setting = $kelas->lmsSetting;

        if (!$setting) {
            return KelasLmsSetting::defaultSetting($kelas->id);
        }

        return [
            'kelas_id'                => $setting->kelas_id,
            'total_pertemuan'         => $setting->total_pertemuan,
            'metode_absensi'          => $setting->metode_absensi,
            'batas_min_hadir_persen'  => $setting->batas_min_hadir_persen,
            'can_submit_late'         => $setting->can_submit_late,
            'show_nilai_to_mahasiswa' => $setting->show_nilai_to_mahasiswa,
            'storage_disk'            => $setting->storage_disk,
            'is_default'              => false,
        ];
    }

    /**
     * Perbarui konfigurasi LMS kelas.
     */
    public function updateKelasLmsSetting(int $kelasId, array $data, ?int $userId = null): KelasLmsSetting
    {
        Kelas::findOrFail($kelasId);
        if ($actorUserId = $userId ?? auth()->id()) {
            $this->assertCanConfigureKelas($kelasId, (int) $actorUserId);
        }

        return KelasLmsSetting::updateOrCreate(
            ['kelas_id' => $kelasId],
            [
                'total_pertemuan'         => $data['total_pertemuan'] ?? 16,
                'metode_absensi'          => $data['metode_absensi'] ?? 'keduanya',
                'batas_min_hadir_persen'  => $data['batas_min_hadir_persen'] ?? 75,
                'can_submit_late'         => $data['can_submit_late'] ?? true,
                'show_nilai_to_mahasiswa' => $data['show_nilai_to_mahasiswa'] ?? true,
                'storage_disk'            => $data['storage_disk'] ?? null,
            ]
        );
    }

    /**
     * Setting bawaan jika kelas belum memiliki konfigurasi khusus.
     */
    protected function getDefaultKelasSetting(Kelas $kelas): array
    {
        return [
            'kelas_id'                => $kelas->id,
            'total_pertemuan'         => 16,
            'metode_absensi'          => 'keduanya',
            'batas_min_hadir_persen'  => 75,
            'can_submit_late'         => true,
            'show_nilai_to_mahasiswa' => true,
            'storage_disk'            => $this->resolveDisk($kelas),
        ];
    }

    /**
     * Salin seluruh materi pertemuan dari kelas sumber ke kelas target,
     * dipasangkan per nomor pertemuan (`pertemuan_ke` sama). Pertemuan target
     * yang belum ada dibuat otomatis (tanggal mengikuti sumber, fallback hari ini
     * karena kolom `siakad_pertemuan.tanggal` NOT NULL).
     *
     * Berkas hanya disalin record-nya (path/disk sama, tanpa duplikat fisik).
     *
     * @param array $opsi ['copy_materi' => bool, 'copy_deskripsi' => bool]
     * @return array{disalin_materi: int, dibuat_pertemuan: int}
     */
    public function importMateriDariKelas(int $targetKelasId, int $sumberKelasId, array $opsi = [], ?int $userId = null): array
    {
        return DB::transaction(function () use ($targetKelasId, $sumberKelasId, $opsi, $userId) {
            Kelas::findOrFail($targetKelasId);
            Kelas::findOrFail($sumberKelasId);
            if ($actorUserId = $userId ?? auth()->id()) {
                $this->assertDosenPengampuKelas($targetKelasId, (int) $actorUserId);
                $this->assertCanViewKelas($sumberKelasId, (int) $actorUserId);
            }

            $copyMateri = $opsi['copy_materi'] ?? true;
            $copyDeskripsi = $opsi['copy_deskripsi'] ?? true;

            if (!$copyMateri) {
                return ['disalin_materi' => 0, 'dibuat_pertemuan' => 0];
            }

            $sumberList = Pertemuan::where('kelas_id', $sumberKelasId)
                ->with(['materiList.files'])
                ->orderBy('pertemuan_ke')
                ->get();

            $disalin = 0;
            $dibuat = 0;

            foreach ($sumberList as $sumber) {
                $target = Pertemuan::where('kelas_id', $targetKelasId)
                    ->where('pertemuan_ke', $sumber->pertemuan_ke)
                    ->first();

                if (!$target) {
                    $target = Pertemuan::create([
                        'kelas_id'         => $targetKelasId,
                        'pertemuan_ke'     => $sumber->pertemuan_ke,
                        'tanggal'          => $sumber->tanggal ?? now()->toDateString(),
                        'materi'           => $sumber->materi,
                        'status_pertemuan' => Pertemuan::STATUS_BELUM,
                    ]);
                    $dibuat++;
                }

                foreach ($sumber->materiList as $materi) {
                    $salinan = MateriPertemuan::create([
                        'pertemuan_id'   => $target->id,
                        'tipe_konten_id' => $materi->tipe_konten_id,
                        'judul'          => $materi->judul,
                        'deskripsi'      => $copyDeskripsi ? $materi->deskripsi : null,
                        'tipe_konten'    => $materi->tipe_konten,
                        'link_eksternal' => $materi->link_eksternal,
                        'urutan'         => $materi->urutan,
                        'is_published'   => $materi->is_published,
                    ]);

                    foreach ($materi->files as $berkas) {
                        MateriFile::create([
                            'materi_id'    => $salinan->id,
                            'nama_file'    => $berkas->nama_file,
                            'file_path'    => $berkas->file_path,
                            'disk'         => $berkas->disk,
                            'ukuran_bytes' => $berkas->ukuran_bytes,
                            'mime_type'    => $berkas->mime_type,
                        ]);
                    }

                    $disalin++;
                }
            }

            return ['disalin_materi' => $disalin, 'dibuat_pertemuan' => $dibuat];
        });
    }

    /**
     * Rekap matriks kehadiran: getRekapAbsensiKelas + `pertemuan_list`
     * (id, pertemuan_ke, tanggal) + `detail_pertemuan` (map
     * mahasiswa_id => [pertemuan_ke => status|null]).
     */
    public function getRekapMatrix(int $kelasId, ?int $onlyMahasiswaId = null): array
    {
        $dasar = $this->getRekapAbsensiKelas($kelasId, $onlyMahasiswaId);

        $kelas = Kelas::with(['pertemuans' => fn ($q) => $q->orderBy('pertemuan_ke')])
            ->findOrFail($kelasId);

        $pertemuanList = $kelas->pertemuans->map(fn (Pertemuan $p) => [
            'id'           => $p->id,
            'pertemuan_ke' => $p->pertemuan_ke,
            'tanggal'      => $p->tanggal,
        ])->values()->all();

        $krsDetails = KrsDetail::with('krs.mahasiswa')
            ->where('kelas_id', $kelasId)
            ->whereHas('krs', fn ($krs) => $krs->whereIn('status', ['disetujui', 'diajukan']))
            ->get();
        $krsDetails = $krsDetails
            ->unique(fn (KrsDetail $detail) => $detail->krs?->mahasiswa_id)
            ->values();

        $detail = [];
        foreach ($krsDetails as $kd) {
            $mhs = $kd->krs?->mahasiswa;
            if (!$mhs) {
                continue;
            }
            if ($onlyMahasiswaId && (int) $mhs->id !== (int) $onlyMahasiswaId) {
                continue;
            }

            $statusByPertemuan = AbsensiMahasiswa::whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
                ->where('mahasiswa_id', $mhs->id)
                ->where('is_submitted', true)
                ->pluck('status', 'pertemuan_id');

            $baris = [];
            foreach ($kelas->pertemuans as $pertemuan) {
                $baris[$pertemuan->pertemuan_ke] = $statusByPertemuan->get($pertemuan->id);
            }
            $detail[$mhs->id] = $baris;
        }

        return array_merge($dasar, [
            'pertemuan_list'   => $pertemuanList,
            'detail_pertemuan' => $detail,
        ]);
    }

    /**
     * Ketercapaian mata kuliah per komponen penilaian OBE kelas.
     *
     * @return array{kelas_id: int, komponen: mixed, ketercapaian: array}
     */
    public function getKetercapaianMk(int $kelasId, ?int $mahasiswaId = null): array
    {
        Kelas::findOrFail($kelasId);

        $komponens = KomponenPenilaian::where('kelas_id', $kelasId)
            ->orderBy('urutan')
            ->get();

        $krsDetails = KrsDetail::with('krs.mahasiswa')
            ->where('kelas_id', $kelasId)
            ->whereHas('krs', function ($k) use ($mahasiswaId) {
                $k->whereIn('status', self::VALID_KRS_STATUSES)
                    ->whereNull('deleted_at')
                    ->when($mahasiswaId, fn ($q) => $q->where('mahasiswa_id', $mahasiswaId));
            })
            ->get();

        $ketercapaian = [];
        foreach ($krsDetails as $kd) {
            $mhs = $kd->krs?->mahasiswa;
            if (!$mhs) {
                continue;
            }

            $nilaiRows = NilaiKomponenMahasiswa::where('krs_detail_id', $kd->id)
                ->whereIn('komponen_penilaian_id', $komponens->pluck('id'))
                ->pluck('nilai_angka', 'komponen_penilaian_id');

            $nilaiPerKomponen = [];
            $total = 0.0;
            $terisi = 0;

            foreach ($komponens as $komponen) {
                $mentah = $nilaiRows->get($komponen->id);
                $nilai = $mentah !== null ? (float) $mentah : null;

                $nilaiPerKomponen[] = [
                    'komponen_penilaian_id' => $komponen->id,
                    'nama_komponen'         => $komponen->nama_komponen,
                    'bobot'                 => (float) $komponen->bobot,
                    'nilai'                 => $nilai,
                    'persen_capaian'        => $nilai !== null ? round($nilai, 2) : null,
                ];

                if ($nilai !== null) {
                    $total += $nilai;
                    $terisi++;
                }
            }

            $rata = $terisi > 0 ? round($total / $terisi, 2) : null;

            $ketercapaian[] = [
                'mahasiswa_id'       => $mhs->id,
                'nim'                => $mhs->nim,
                'nama_lengkap'       => $mhs->nama_lengkap,
                'nilai_per_komponen' => $nilaiPerKomponen,
                'rata_rata'          => $rata,
                'persen_capaian'     => $rata,
            ];
        }

        return [
            'kelas_id'     => $kelasId,
            'komponen'     => $komponens,
            'ketercapaian' => $ketercapaian,
        ];
    }
}
