<?php

namespace App\Services\Mobile;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\TahunAkademik;
use App\Models\User;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\DokumenPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Arsip\NomorSurat;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sinapra\MaintenanceLog;
use App\Models\PeminjamanRuangan;
use App\Models\PeminjamanAset;
use App\Models\Lms\MateriPertemuan;
use App\Models\Lms\LmsNotifikasi;
use App\Models\Spmb\ReferralUsage;
use App\Services\AuditLogService;
use App\Services\Spmb\SpmbReferralService;
use App\Services\Sinapra\MaintenanceService;
use App\Services\Storage\FileStorageService;
use App\Services\Simpeg\PresensiPengajaranService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileStaffService
{
    public function __construct(
        protected ?SpmbReferralService $spmbReferralService = null,
        protected ?MaintenanceService $maintenanceService = null,
        protected ?FileStorageService $fileStorageService = null
    ) {
        $this->spmbReferralService = $spmbReferralService ?? app(SpmbReferralService::class);
        $this->maintenanceService = $maintenanceService ?? app(MaintenanceService::class);
        $this->fileStorageService = $fileStorageService ?? app(FileStorageService::class);
    }
    /**
     * Resolve Dosen by user ID, NIDN, NIP, or username.
     */
    public function resolveDosen(?int $userId = null, ?string $nidn = null, ?string $nip = null, ?string $username = null): ?Dosen
    {
        $query = Dosen::query();

        if ($userId) {
            $dosen = (clone $query)->where('user_id', $userId)->first();
            if ($dosen) return $dosen;
        }

        $identifier = $nidn ?: $username;
        if ($identifier) {
            $clean = trim($identifier, "'\" ");
            $noZero = ltrim($clean, '0');
            $padded = str_pad($noZero, 10, '0', STR_PAD_LEFT);

            $dosen = (clone $query)->where(function ($q) use ($clean, $noZero, $padded) {
                $q->where('nidn', $clean)
                  ->orWhere('nidn', $noZero)
                  ->orWhere('nidn', $padded)
                  ->orWhere('nip', $clean);
            })->first();

            if ($dosen) return $dosen;
        }

        if ($nip) {
            $cleanNip = trim($nip, "'\" ");
            $dosen = (clone $query)->where('nip', $cleanNip)->first();
            if ($dosen) return $dosen;
        }

        return null;
    }

    /**
     * Dapatkan jadwal mengajar dosen (hari ini & seluruh semester).
     */
    public function getJadwalMengajar(
        ?int $userId = null,
        ?string $nidn = null,
        ?string $nip = null,
        ?string $username = null,
        ?string $hari = null,
        ?int $tahunAkademikId = null,
        ?string $sortBy = 'jam_mulai',
        ?string $sortOrder = 'asc'
    ): array {
        $dosen = $this->resolveDosen($userId, $nidn, $nip, $username);
        $todayDay = $this->getIndonesianDayName(now());
        $nowTime = now()->format('H:i:s');
        $sortOrder = strtolower($sortOrder ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['jam_mulai', 'kode_kelas', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'jam_mulai';

        // Jika dosen belum ditemukan di siakad_dosen
        if (!$dosen) {
            return [
                'dosen' => [
                    'id' => null,
                    'nama_lengkap' => $username ?? 'Dosen',
                    'nama_gelar' => $username ?? 'Dosen',
                    'nidn' => $nidn ?? '-',
                    'nip' => $nip ?? '-',
                    'is_fallback' => false,
                ],
                'today_day' => $todayDay,
                'filter_day' => $hari,
                'today_classes' => [],
                'all_classes' => [],
                'total_sks' => 0,
                'total_kelas' => 0,
            ];
        }

        $tahunAkademik = $tahunAkademikId
            ? TahunAkademik::find($tahunAkademikId)
            : TahunAkademik::where('is_active', true)->first();

        // Ambil kelas yang diampu oleh dosen ini
        $query = Kelas::with([
            'mataKuliah',
            'ruangan.gedung',
            'programStudi',
            'dosenPengampu',
        ])
        ->withCount(['krsDetails as total_mahasiswa' => fn($q) => $q->where('status', 'aktif')])
        ->whereHas('dosenPengampu', fn($dp) => $dp->where('dosen_id', $dosen->id))
        ->when($tahunAkademik, fn($q) => $q->where('tahun_akademik_id', $tahunAkademik->id));

        $classes = $query->orderByRaw("
            CASE LOWER(hari)
                WHEN 'senin' THEN 1
                WHEN 'selasa' THEN 2
                WHEN 'rabu' THEN 3
                WHEN 'kamis' THEN 4
                WHEN 'jumat' THEN 5
                WHEN 'sabtu' THEN 6
                WHEN 'minggu' THEN 7
                ELSE 8
            END ASC
        ")->orderBy($sortBy, $sortOrder)->get();

        $formattedClasses = $classes->map(
            fn($k) => $this->formatKelasItem($k, $todayDay, $nowTime, $dosen)
        );

        $todayClasses = $formattedClasses->where('is_today', true)->values()->all();

        $finalClasses = $hari
            ? $formattedClasses->filter(fn($c) => strtolower($c['hari']) === strtolower($hari))->values()->all()
            : $formattedClasses->values()->all();

        return [
            'dosen' => [
                'id' => $dosen->id,
                'nama_lengkap' => $dosen->nama_lengkap,
                'nama_gelar' => $dosen->nama_gelar ?? $dosen->nama_lengkap,
                'nidn' => $dosen->nidn,
                'nip' => $dosen->nip,
                'is_fallback' => false,
            ],
            'today_day' => $todayDay,
            'filter_day' => $hari,
            'today_classes' => $todayClasses,
            'all_classes' => $finalClasses,
            'total_sks' => (int) $formattedClasses->sum('sks'),
            'total_kelas' => $formattedClasses->count(),
        ];
    }

    /**
     * Format item kelas untuk respon API jadwal mengajar.
     */
    protected function formatKelasItem(Kelas $k, string $todayDay, string $nowTime, Dosen $dosen): array
    {
        $isToday = strtolower($k->hari ?? '') === strtolower($todayDay);
        $start = $k->jam_mulai ? substr($k->jam_mulai, 0, 5) : '08:00';
        $end = $k->jam_selesai ? substr($k->jam_selesai, 0, 5) : '10:30';

        $isOngoing = false;
        $status = 'Terjadwal';
        if ($isToday) {
            if ($nowTime >= $k->jam_mulai && $nowTime <= $k->jam_selesai) {
                $isOngoing = true;
                $status = 'Sedang Berlangsung';
            } elseif ($nowTime < $k->jam_mulai) {
                $diffMin = Carbon::parse($k->jam_mulai)->diffInMinutes(now());
                $status = $diffMin <= 60 ? "Mulai dlm {$diffMin} Menit" : 'Akan Datang';
            } else {
                $status = 'Selesai';
            }
        }

        $dp = $k->dosenPengampu->firstWhere('dosen_id', $dosen->id);
        $peran = $dp?->peran ?? DosenPengampu::DEFAULT_PERAN;

        return [
            'id' => $k->id,
            'course_code' => $k->mataKuliah?->kode_mk ?? $k->kode_kelas,
            'course_name' => $k->mataKuliah?->nama ?? $k->nama_kelas,
            'sks' => (int) ($k->mataKuliah?->total_sks ?? 3),
            'class_name' => $k->nama_kelas,
            'room' => $k->ruangan?->nama ?? $k->ruangan?->nama_ruang ?? 'Ruang Teori',
            'room_code' => $k->ruangan?->kode ?? '-',
            'start_time' => $start,
            'end_time' => $end,
            'student_count' => (int) ($k->total_mahasiswa > 0 ? $k->total_mahasiswa : ($k->kapasitas ?: 30)),
            'session' => 10,
            'status' => $status,
            'hari' => $k->hari ?? '-',
            'is_today' => $isToday,
            'is_ongoing' => $isOngoing,
            'reminder_active' => true,
            'peran' => $peran,
        ];
    }

    /**
     * Nama hari dalam bahasa Indonesia.
     */
    public function getIndonesianDayName(Carbon $date): string
    {
        $days = [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ];
        return $days[$date->dayOfWeekIso] ?? 'senin';
    }

    /**
     * Daftar kelas yang diampu dosen untuk input nilai.
     */
    public function getKelasListForGrading(?int $userId = null, ?string $nidn = null, ?string $nip = null, ?string $username = null): array
    {
        $dosen = $this->resolveDosen($userId, $nidn, $nip, $username);
        if (!$dosen) {
            return [];
        }

        $tahunAkademik = TahunAkademik::where('is_active', true)->first();

        $query = Kelas::with(['mataKuliah', 'programStudi', 'krsDetails.nilaiMahasiswa'])
            ->whereHas('dosenPengampu', fn($q) => $q->where('dosen_id', $dosen->id));

        if ($tahunAkademik) {
            $query->where('tahun_akademik_id', $tahunAkademik->id);
        }

        $kelasList = $query->get();

        if ($kelasList->isEmpty()) {
            return [];
        }

        return $kelasList->map(function ($k) {
            $totalMhs = $k->krsDetails->count();
            $gradedCount = $k->krsDetails->filter(fn($kd) => $kd->nilaiMahasiswa && $kd->nilaiMahasiswa->nilai_akhir !== null)->count();

            $status = 'belum_dinilai';
            if ($totalMhs > 0 && $gradedCount === $totalMhs) {
                $status = 'selesai';
            } elseif ($gradedCount > 0) {
                $status = 'draft';
            }

            return [
                'kelas_id' => $k->id,
                'kode_kelas' => $k->kode_kelas,
                'nama_kelas' => $k->nama_kelas,
                'kode_mk' => $k->mataKuliah?->kode_mk ?? '-',
                'nama_mk' => $k->mataKuliah?->nama ?? $k->nama_kelas,
                'sks' => (int) ($k->mataKuliah?->total_sks ?? 3),
                'prodi' => $k->programStudi?->nama ?? '-',
                'hari' => $k->hari ?? '-',
                'jam' => ($k->jam_mulai ? substr($k->jam_mulai, 0, 5) : '08:00') . ' - ' . ($k->jam_selesai ? substr($k->jam_selesai, 0, 5) : '10:30'),
                'total_mahasiswa' => $totalMhs,
                'jumlah_dinilai' => $gradedCount,
                'status_penilaian' => $status,
                'bobot' => $k->getBobotPenilaian(),
            ];
        })->values()->toArray();
    }

    /**
     * Detail kelas beserta daftar mahasiswa & komponen nilai.
     */
    public function getKelasGradeDetail(int $kelasId): ?array
    {
        $kelas = Kelas::with([
            'mataKuliah',
            'programStudi',
            'krsDetails.krs.mahasiswa',
            'krsDetails.nilaiMahasiswa'
        ])->find($kelasId);

        if (!$kelas) {
            return null;
        }

        $bobot = $kelas->getBobotPenilaian();

        $students = $kelas->krsDetails->map(function ($kd) {
            $mhs = $kd->krs?->mahasiswa;
            $nilai = $kd->nilaiMahasiswa;

            return [
                'krs_detail_id' => $kd->id,
                'mahasiswa_id' => $mhs?->id,
                'nim' => $mhs?->nim ?? '-',
                'nama' => $mhs?->nama_lengkap ?? $mhs?->nama ?? '-',
                'avatar' => $mhs?->avatar,
                'nilai_harian' => $nilai ? (float) $nilai->nilai_harian : null,
                'nilai_uts' => $nilai ? (float) $nilai->nilai_uts : null,
                'nilai_uas' => $nilai ? (float) $nilai->nilai_uas : null,
                'nilai_praktik' => $nilai ? (float) $nilai->nilai_praktik : null,
                'nilai_akhir' => $nilai ? (float) $nilai->nilai_akhir : null,
                'nilai_huruf' => $nilai?->nilai_huruf,
                'bobot_mutu' => $nilai ? (float) $nilai->bobot_mutu : null,
                'is_final' => (bool) ($nilai?->is_final ?? false),
            ];
        })->values()->toArray();

        return [
            'kelas_id' => $kelas->id,
            'kode_kelas' => $kelas->kode_kelas,
            'nama_kelas' => $kelas->nama_kelas,
            'kode_mk' => $kelas->mataKuliah?->kode_mk ?? '-',
            'nama_mk' => $kelas->mataKuliah?->nama ?? $kelas->nama_kelas,
            'sks' => (int) ($kelas->mataKuliah?->total_sks ?? 3),
            'prodi' => $kelas->programStudi?->nama ?? '-',
            'bobot' => $bobot,
            'students' => $students,
        ];
    }

    /**
     * Simpan / Perbarui nilai mahasiswa pada kelas.
     */
    public function saveKelasGrades(int $kelasId, array $grades, ?int $userId = null, ?Request $request = null): array
    {
        $kelas = Kelas::find($kelasId);
        $bobot = $kelas ? $kelas->getBobotPenilaian() : Kelas::defaultBobotPenilaian();
        $prodiId = $kelas?->program_studi_id;
        $updatedCount = 0;

        foreach ($grades as $item) {
            $krsDetailId = $item['krs_detail_id'] ?? null;
            if (!$krsDetailId) continue;

            $harian = isset($item['nilai_harian']) && $item['nilai_harian'] !== null && $item['nilai_harian'] !== '' ? (float) $item['nilai_harian'] : null;
            $uts = isset($item['nilai_uts']) && $item['nilai_uts'] !== null && $item['nilai_uts'] !== '' ? (float) $item['nilai_uts'] : null;
            $uas = isset($item['nilai_uas']) && $item['nilai_uas'] !== null && $item['nilai_uas'] !== '' ? (float) $item['nilai_uas'] : null;
            $praktik = isset($item['nilai_praktik']) && $item['nilai_praktik'] !== null && $item['nilai_praktik'] !== '' ? (float) $item['nilai_praktik'] : null;

            $nilaiAkhir = null;
            $nilaiHuruf = null;
            $bobotMutu = null;

            if ($harian !== null || $uts !== null || $uas !== null || $praktik !== null) {
                $h = $harian ?? 0;
                $t = $uts ?? 0;
                $u = $uas ?? 0;
                $p = $praktik ?? 0;

                $nilaiAkhir = round(
                    ($h * ($bobot['harian'] ?? 0.2)) +
                    ($t * ($bobot['uts'] ?? 0.25)) +
                    ($u * ($bobot['uas'] ?? 0.35)) +
                    ($p * ($bobot['praktik'] ?? 0.2)),
                    2
                );

                $skala = SkalaNilai::konversiNilai($nilaiAkhir, $prodiId);
                if ($skala) {
                    $nilaiHuruf = $skala->nilai_huruf;
                    $bobotMutu = (float) $skala->bobot_indeks;
                } else {
                    [$nilaiHuruf, $bobotMutu] = $this->calculateGradeScale($nilaiAkhir);
                }
            }

            $nilaiModel = NilaiMahasiswa::firstOrNew(['krs_detail_id' => $krsDetailId]);
            $oldValues = $nilaiModel->exists ? $nilaiModel->getOriginal() : null;
            $isNew = !$nilaiModel->exists;

            $nilaiModel->fill([
                'nilai_harian' => $harian,
                'nilai_uts' => $uts,
                'nilai_uas' => $uas,
                'nilai_praktik' => $praktik,
                'nilai_akhir' => $nilaiAkhir,
                'nilai_huruf' => $nilaiHuruf,
                'bobot_mutu' => $bobotMutu,
                'is_final' => !empty($item['is_final']),
                'diinput_oleh' => $userId,
            ]);

            $nilaiModel->save();

            try {
                AuditLogService::record(
                    module: 'siakad',
                    action: $isNew ? 'create_nilai_mahasiswa' : 'update_nilai_mahasiswa',
                    tableName: 'siakad_nilai_mahasiswa',
                    recordId: $nilaiModel->id,
                    oldValues: $oldValues,
                    newValues: $nilaiModel->getChanges(),
                    request: $request
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log nilai mahasiswa: ' . $e->getMessage());
            }

            $updatedCount++;
        }

        return [
            'kelas_id' => $kelasId,
            'updated_count' => $updatedCount,
            'message' => "Berhasil menyimpan {$updatedCount} nilai mahasiswa.",
        ];
    }

    /**
     * Konversi angka nilai ke huruf mutu & bobot standar Poltek Indonusa.
     */
    private function calculateGradeScale(float $score): array
    {
        if ($score >= 85) return ['A', 4.00];
        if ($score >= 80) return ['A-', 3.75];
        if ($score >= 75) return ['B+', 3.50];
        if ($score >= 70) return ['B', 3.00];
        if ($score >= 65) return ['B-', 2.75];
        if ($score >= 60) return ['C+', 2.50];
        if ($score >= 55) return ['C', 2.00];
        if ($score >= 45) return ['D', 1.00];
        return ['E', 0.00];
    }

    /**
     * Daftar mahasiswa bimbingan akademik (DPA).
     */
    public function getAdviseesList(?int $userId = null, ?string $nidn = null, ?string $nip = null, ?string $username = null): array
    {
        $dosen = $this->resolveDosen($userId, $nidn, $nip, $username);
        if (!$dosen) {
            return [];
        }

        $tahunAkademik = TahunAkademik::where('is_active', true)->first();

        $query = Mahasiswa::with(['programStudi', 'krs' => function ($q) use ($tahunAkademik) {
            if ($tahunAkademik) {
                $q->where('tahun_akademik_id', $tahunAkademik->id);
            }
            $q->orderByDesc('id');
        }])
        ->where('dosen_wali_id', $dosen->id);

        $advisees = $query->get();

        if ($advisees->isEmpty()) {
            return [];
        }

        return $advisees->map(function ($m) {
            $latestKrs = $m->krs->first();
            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama_lengkap ?? $m->nama ?? 'Mahasiswa',
                'avatar' => $m->avatar,
                'prodi' => $m->programStudi?->nama ?? '-',
                'angkatan' => $m->angkatan ?? ($m->tahun_masuk ? (string) $m->tahun_masuk : '-'),
                'semester' => (int) ($m->semester_aktif ?? 1),
                'ipk' => (float) ($m->ipk ?? 0.0),
                'total_sks_lulus' => (int) ($m->total_sks_lulus ?? $m->total_sks ?? 0),
                'krs' => [
                    'id' => $latestKrs?->id,
                    'status' => $latestKrs?->status ?? 'belum_krs',
                    'total_sks' => (int) ($latestKrs?->total_sks_diambil ?? 0),
                    'disetujui_at' => $latestKrs?->disetujui_at?->toIso8601String(),
                ],
            ];
        })->values()->toArray();
    }

    /**
     * Detail rincian KRS mahasiswa bimbingan.
     */
    public function getKrsDetail(int $krsId): ?array
    {
        $krs = Krs::with([
            'mahasiswa.programStudi',
            'tahunAkademik',
            'krsDetails.kelas.mataKuliah',
            'krsDetails.kelas.ruangan'
        ])->find($krsId);

        if (!$krs) {
            return null;
        }

        $items = $krs->krsDetails->map(function ($kd) {
            $kelas = $kd->kelas;
            return [
                'krs_detail_id' => $kd->id,
                'kelas_id' => $kelas?->id,
                'kode_mk' => $kelas?->mataKuliah?->kode_mk ?? '-',
                'nama_mk' => $kelas?->mataKuliah?->nama ?? $kelas?->nama_kelas ?? '-',
                'sks' => (int) ($kelas?->mataKuliah?->total_sks ?? 3),
                'kelas' => $kelas?->nama_kelas ?? 'Kelas',
                'hari' => $kelas?->hari ?? '-',
                'jam' => ($kelas?->jam_mulai ? substr($kelas->jam_mulai, 0, 5) : '08:00') . ' - ' . ($kelas?->jam_selesai ? substr($kelas->jam_selesai, 0, 5) : '10:30'),
                'ruangan' => $kelas?->ruangan?->nama ?? $kelas?->ruangan?->nama_ruang ?? '-',
            ];
        })->values()->toArray();

        return [
            'krs_id' => $krs->id,
            'mahasiswa' => [
                'id' => $krs->mahasiswa?->id,
                'nim' => $krs->mahasiswa?->nim,
                'nama' => $krs->mahasiswa?->nama_lengkap ?? $krs->mahasiswa?->nama ?? 'Mahasiswa',
                'prodi' => $krs->mahasiswa?->programStudi?->nama ?? '-',
                'semester' => (int) ($krs->mahasiswa?->semester_aktif ?? 1),
                'ipk' => (float) ($krs->mahasiswa?->ipk ?? 0.0),
            ],
            'tahun_akademik' => $krs->tahunAkademik?->nama ?? '-',
            'total_sks' => (int) $krs->total_sks_diambil,
            'status' => $krs->status,
            'disetujui_at' => $krs->disetujui_at?->toIso8601String(),
            'matakuliah' => $items,
        ];
    }

    /**
     * Setujui KRS Mahasiswa Bimbingan.
     */
    public function approveKrs(int $krsId, ?int $userId = null): bool
    {
        $krs = Krs::with('mahasiswa')->find($krsId);
        if (!$krs) {
            return false;
        }

        $dosen = $this->resolveDosen($userId);
        $krs->status = 'disetujui';
        $krs->disetujui_oleh = $dosen?->id ?? $userId;
        $krs->disetujui_at = now();
        return $krs->save();
    }

    /**
     * Minta revisi / tolak KRS Mahasiswa Bimbingan.
     */
    public function rejectKrs(int $krsId, ?string $alasan = null, ?int $userId = null): bool
    {
        $krs = Krs::with('mahasiswa')->find($krsId);
        if (!$krs) {
            return false;
        }

        $krs->status = 'revisi';
        $saved = $krs->save();

        if ($alasan && $krs->mahasiswa_id) {
            $dosen = $this->resolveDosen($userId);
            $dosenId = $dosen?->id ?? $krs->mahasiswa?->dosen_wali_id;

            if ($dosenId) {
                PaCatatan::create([
                    'dosen_id' => $dosenId,
                    'mahasiswa_id' => $krs->mahasiswa_id,
                    'tahun_akademik_id' => $krs->tahun_akademik_id,
                    'tanggal_bimbingan' => now(),
                    'kategori' => 'revisi_krs',
                    'isi' => "Permintaan revisi KRS: " . $alasan,
                    'kesimpulan' => 'Perlu revisi pemilihan mata kuliah',
                    'dibuat_oleh' => $userId,
                ]);
            }
        }

        return $saved;
    }

    /**
     * Riwayat catatan bimbingan akademik mahasiswa.
     */
    public function getCatatanBimbingan(int $mahasiswaId): array
    {
        $records = PaCatatan::with('dosen')
            ->where('mahasiswa_id', $mahasiswaId)
            ->orderByDesc('tanggal_bimbingan')
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        return $records->map(function ($c) {
            return [
                'id' => $c->id,
                'tanggal' => $c->tanggal_bimbingan?->format('d M Y') ?? now()->format('d M Y'),
                'kategori' => $c->kategori ?? 'Akademik',
                'isi' => $c->isi,
                'kesimpulan' => $c->kesimpulan,
                'dosen' => $c->dosen?->nama_gelar ?? $c->dosen?->nama_lengkap ?? 'Dosen PA',
            ];
        })->values()->toArray();
    }

    /**
     * Tambah catatan bimbingan baru.
     */
    public function storeCatatanBimbingan(int $mahasiswaId, array $data, ?int $userId = null): ?array
    {
        $mahasiswa = Mahasiswa::find($mahasiswaId);
        if (!$mahasiswa) {
            return null;
        }

        $dosen = $this->resolveDosen($userId);
        $dosenId = $dosen?->id ?? $mahasiswa->dosen_wali_id;
        if (!$dosenId) {
            return null;
        }

        $tahunAkademik = TahunAkademik::where('is_active', true)->first();

        $catatan = PaCatatan::create([
            'dosen_id' => $dosenId,
            'mahasiswa_id' => $mahasiswaId,
            'tahun_akademik_id' => $tahunAkademik?->id,
            'tanggal_bimbingan' => $data['tanggal'] ?? now(),
            'kategori' => $data['kategori'] ?? 'Akademik',
            'isi' => $data['isi'] ?? '',
            'kesimpulan' => $data['kesimpulan'] ?? null,
            'butuh_penanganan_khusus' => !empty($data['butuh_penanganan_khusus']),
            'dibuat_oleh' => $userId,
        ]);

        return [
            'id' => $catatan->id,
            'tanggal' => $catatan->tanggal_bimbingan?->format('d M Y'),
            'kategori' => $catatan->kategori,
            'isi' => $catatan->isi,
            'kesimpulan' => $catatan->kesimpulan,
        ];
    }

    // ================================================================
    // MODUL PERKULIAHAN: JURNAL PERTEMUAN (BAP) & PRESENSI MAHASISWA
    // ================================================================

    /**
     * Daftar kelas yang diampu dosen dengan ringkasan progres pertemuan (BAP).
     */
    public function getKelasListForPertemuan(?int $userId = null, ?string $nidn = null, ?string $nip = null, ?string $username = null): array
    {
        $dosen = $this->resolveDosen($userId, $nidn, $nip, $username);
        if (!$dosen) {
            return [];
        }

        $tahunAkademik = TahunAkademik::where('is_active', true)->first();

        $query = Kelas::with(['mataKuliah', 'tahunAkademik', 'ruangan'])
            ->whereHas('dosenPengampu', fn($q) => $q->where('dosen_id', $dosen->id));

        if ($tahunAkademik) {
            $query->where('tahun_akademik_id', $tahunAkademik->id);
        }

        $classes = $query->get();

        if ($classes->isEmpty()) {
            $classes = Kelas::with(['mataKuliah', 'tahunAkademik', 'ruangan'])
                ->whereHas('dosenPengampu', fn($q) => $q->where('dosen_id', $dosen->id))
                ->get();
        }

        return $classes->map(function ($k) {
            $pertemuanCount = Pertemuan::where('kelas_id', $k->id)->count();
            $pertemuanSelesai = Pertemuan::where('kelas_id', $k->id)
                ->where('status_pertemuan', Pertemuan::STATUS_SELESAI)
                ->count();
            $pertemuanAktif = Pertemuan::where('kelas_id', $k->id)
                ->where('status_pertemuan', Pertemuan::STATUS_BERLANGSUNG)
                ->first();

            $totalMhs = KrsDetail::where('kelas_id', $k->id)->count();

            return [
                'kelas_id' => $k->id,
                'kode_kelas' => $k->kode_kelas ?? 'KELAS-' . $k->id,
                'nama_kelas' => $k->nama_kelas,
                'kode_mk' => $k->mataKuliah?->kode_mk ?? '-',
                'nama_mk' => $k->mataKuliah?->nama ?? $k->mataKuliah?->nama_mk ?? '-',
                'sks' => $k->mataKuliah?->total_sks ?? $k->mataKuliah?->sks_total ?? 3,
                'ruangan' => $k->ruangan?->nama_ruangan ?? 'Ruang Kuliah',
                'hari' => $k->hari ?? 'Senin',
                'jam' => ($k->jam_mulai ? substr($k->jam_mulai, 0, 5) : '08:00') . ' - ' . ($k->jam_selesai ? substr($k->jam_selesai, 0, 5) : '10:30'),
                'total_mahasiswa' => $totalMhs,
                'total_pertemuan' => 16,
                'pertemuan_tercatat' => $pertemuanCount,
                'pertemuan_selesai' => $pertemuanSelesai,
                'has_active_session' => $pertemuanAktif !== null,
                'active_session_id' => $pertemuanAktif?->id,
                'active_session_ke' => $pertemuanAktif?->pertemuan_ke,
            ];
        })->values()->toArray();
    }

    /**
     * Ambil rincian kelas dan daftar seluruh pertemuan 1 s/d 16.
     */
    public function getPertemuanList(int $kelasId): ?array
    {
        $kelas = Kelas::with(['mataKuliah', 'tahunAkademik', 'ruangan'])->find($kelasId);
        if (!$kelas) {
            return null;
        }

        $totalMhs = KrsDetail::where('kelas_id', $kelasId)->count();

        // Ambil pertemuan yang sudah tersimpan
        $existingPertemuan = Pertemuan::where('kelas_id', $kelasId)
            ->orderBy('pertemuan_ke')
            ->get()
            ->keyBy('pertemuan_ke');

        $list = [];
        for ($i = 1; $i <= 16; $i++) {
            $p = $existingPertemuan->get($i);
            if ($p) {
                // Hitung statistik presensi
                $hadir = AbsensiMahasiswa::where('pertemuan_id', $p->id)->where('status', AbsensiMahasiswa::STATUS_HADIR)->count();
                $izin = AbsensiMahasiswa::where('pertemuan_id', $p->id)->where('status', AbsensiMahasiswa::STATUS_IZIN)->count();
                $sakit = AbsensiMahasiswa::where('pertemuan_id', $p->id)->where('status', AbsensiMahasiswa::STATUS_SAKIT)->count();
                $alfa = AbsensiMahasiswa::where('pertemuan_id', $p->id)->where('status', AbsensiMahasiswa::STATUS_ALFA)->count();

                $list[] = [
                    'id' => $p->id,
                    'pertemuan_ke' => $i,
                    'tanggal' => $p->tanggal ? Carbon::parse($p->tanggal)->format('d M Y') : null,
                    'tanggal_raw' => $p->tanggal ? Carbon::parse($p->tanggal)->toDateString() : null,
                    'jam_mulai' => $p->jam_mulai ? substr($p->jam_mulai, 0, 5) : null,
                    'jam_selesai' => $p->jam_selesai ? substr($p->jam_selesai, 0, 5) : null,
                    'materi' => $p->materi,
                    'catatan' => $p->catatan_pertemuan,
                    'status' => $p->status_pertemuan ?? 'belum',
                    'token_absensi' => $p->token_absensi,
                    'is_active' => $p->status_pertemuan === Pertemuan::STATUS_BERLANGSUNG,
                    'total_hadir' => $hadir,
                    'total_izin' => $izin,
                    'total_sakit' => $sakit,
                    'total_alfa' => $alfa,
                    'total_mahasiswa' => $totalMhs,
                ];
            } else {
                // Skeleton pertemuan yang belum pernah dibuka
                $list[] = [
                    'id' => null,
                    'pertemuan_ke' => $i,
                    'tanggal' => null,
                    'tanggal_raw' => null,
                    'jam_mulai' => null,
                    'jam_selesai' => null,
                    'materi' => null,
                    'catatan' => null,
                    'status' => 'belum',
                    'token_absensi' => null,
                    'is_active' => false,
                    'total_hadir' => 0,
                    'total_izin' => 0,
                    'total_sakit' => 0,
                    'total_alfa' => 0,
                    'total_mahasiswa' => $totalMhs,
                ];
            }
        }

        $pertemuanSelesai = collect($list)->where('status', 'selesai')->count();
        $pertemuanAktif = collect($list)->firstWhere('is_active', true);

        return [
            'kelas' => [
                'id' => $kelas->id,
                'kelas_id' => $kelas->id,
                'kode_kelas' => $kelas->kode_kelas ?? 'KELAS-' . $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'kode_mk' => $kelas->mataKuliah?->kode_mk ?? '-',
                'nama_mk' => $kelas->mataKuliah?->nama ?? $kelas->mataKuliah?->nama_mk ?? '-',
                'sks' => $kelas->mataKuliah?->total_sks ?? $kelas->mataKuliah?->sks_total ?? 3,
                'ruangan' => $kelas->ruangan?->nama_ruangan ?? 'Ruang Kuliah',
                'hari' => $kelas->hari ?? 'Senin',
                'jam' => ($kelas->jam_mulai ? substr($kelas->jam_mulai, 0, 5) : '08:00') . ' - ' . ($kelas->jam_selesai ? substr($kelas->jam_selesai, 0, 5) : '10:30'),
                'total_mahasiswa' => $totalMhs,
                'pertemuan_selesai' => $pertemuanSelesai,
                'has_active_session' => $pertemuanAktif !== null,
                'active_session_ke' => $pertemuanAktif['pertemuan_ke'] ?? null,
            ],
            'pertemuan' => $list,
        ];
    }

    /**
     * Memulai sesi perkuliahan & buka presensi kelas mahasiswa.
     */
    public function mulaiSesiPertemuan(int $kelasId, array $data, ?int $userId = null): ?array
    {
        $kelas = Kelas::find($kelasId);
        if (!$kelas) {
            return null;
        }

        $pertemuanKe = (int) ($data['pertemuan_ke'] ?? 1);
        if ($pertemuanKe < 1 || $pertemuanKe > 16) {
            $pertemuanKe = 1;
        }

        // Generate token dinamis 6-digit untuk mahasiswa scan/input
        $tokenAbsensi = (string) random_int(100000, 999999);

        // Cari atau buat record pertemuan
        $pertemuan = Pertemuan::firstOrNew([
            'kelas_id' => $kelasId,
            'pertemuan_ke' => $pertemuanKe,
        ]);

        $isNew = !$pertemuan->exists;
        $oldValues = $pertemuan->exists ? $pertemuan->getOriginal() : null;

        $tanggal = !empty($data['tanggal']) ? Carbon::parse($data['tanggal'])->toDateString() : Carbon::today()->toDateString();
        $jamMulai = !empty($data['jam_mulai']) ? $data['jam_mulai'] : Carbon::now()->format('H:i');

        $pertemuan->tanggal = $tanggal;

        // Membuka sesi mobile juga menulis entri absensi awal mahasiswa,
        // sehingga tetap tunduk pada cut-off penulisan presensi.
        app(PresensiPengajaranService::class)->assertWriteAllowed(
            $pertemuan,
            (int) ($userId ?? auth()->id())
        );

        $pertemuan->jam_mulai = $jamMulai;
        $pertemuan->materi = $data['materi'] ?? ('Pertemuan ke-' . $pertemuanKe . ' - ' . ($kelas->mataKuliah?->nama ?? $kelas->mataKuliah?->nama_mk ?? ''));
        $pertemuan->catatan_pertemuan = $data['catatan'] ?? null;
        $pertemuan->status_pertemuan = Pertemuan::STATUS_BERLANGSUNG;
        $pertemuan->token_absensi = $tokenAbsensi;
        $pertemuan->token_expired_at = Carbon::now()->addHours(2);
        $pertemuan->window_menit = 120;
        $pertemuan->presensi_closed_at = null;
        $pertemuan->save();

        $newValues = $isNew ? $pertemuan->getAttributes() : $pertemuan->getChanges();

        try {
            AuditLogService::record(
                module: 'siakad',
                action: $isNew ? 'start_perkuliahan_session' : 'update_perkuliahan_session',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuan->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log buka perkuliahan: ' . $e->getMessage());
        }

        // Inisialisasi entri presensi default untuk semua mahasiswa di kelas ini jika belum ada
        $peserta = KrsDetail::with('krs')->where('kelas_id', $kelasId)->get();
        foreach ($peserta as $p) {
            $mhsId = $p->mahasiswa_id ?? $p->krs?->mahasiswa_id;
            if (!$mhsId) continue;

            AbsensiMahasiswa::firstOrCreate(
                [
                    'pertemuan_id' => $pertemuan->id,
                    'mahasiswa_id' => $mhsId,
                ],
                [
                    'status' => AbsensiMahasiswa::STATUS_HADIR,
                    'catatan' => null,
                    'is_submitted' => false,
                ]
            );
        }

        return [
            'id' => $pertemuan->id,
            'kelas_id' => $kelasId,
            'pertemuan_ke' => $pertemuan->pertemuan_ke,
            'tanggal' => Carbon::parse($pertemuan->tanggal)->format('d M Y'),
            'materi' => $pertemuan->materi,
            'catatan' => $pertemuan->catatan_pertemuan,
            'status' => $pertemuan->status_pertemuan,
            'token_absensi' => $pertemuan->token_absensi,
            'token_expired_at' => $pertemuan->token_expired_at?->toIso8601String(),
            'jam_mulai' => $pertemuan->jam_mulai,
            'qr_payload' => json_encode([
                'type' => 'siakad_presensi',
                'pertemuan_id' => $pertemuan->id,
                'kelas_id' => $kelasId,
                'pertemuan_ke' => $pertemuan->pertemuan_ke,
                'token' => $tokenAbsensi,
                'timestamp' => Carbon::now()->timestamp,
            ]),
        ];
    }

    /**
     * Putar ulang token absensi pertemuan (Dynamic rotating token per 30 detik).
     */
    public function rotateTokenPertemuan(int $pertemuanId, ?int $userId = null): ?array
    {
        $pertemuan = Pertemuan::find($pertemuanId);
        if (!$pertemuan) {
            return null;
        }

        if ($pertemuan->status_pertemuan === Pertemuan::STATUS_SELESAI || $pertemuan->presensi_closed_at) {
            return null;
        }

        // Grace period 10 detik simpan token lama di cache
        if (!empty($pertemuan->token_absensi)) {
            \Illuminate\Support\Facades\Cache::put("lms:presensi:prev_token:{$pertemuanId}", (string) $pertemuan->token_absensi, now()->addSeconds(10));
        }

        $oldValues = $pertemuan->getOriginal();
        $token = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $rotateSeconds = 30;
        $expiredAt = Carbon::now()->addSeconds($rotateSeconds);

        $pertemuan->token_absensi = $token;
        $pertemuan->token_expired_at = $expiredAt;
        $pertemuan->token_rotated_at = Carbon::now();
        $pertemuan->save();

        try {
            AuditLogService::record(
                module: 'siakad',
                action: 'rotate_attendance_token',
                tableName: 'siakad_pertemuan',
                recordId: $pertemuan->id,
                oldValues: $oldValues,
                newValues: $pertemuan->getChanges(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log rotate token: ' . $e->getMessage());
        }

        return [
            'pertemuan_id' => $pertemuanId,
            'kelas_id' => $pertemuan->kelas_id,
            'token_absensi' => $token,
            'qr_payload' => "RAG-PRESENSI:{$pertemuanId}:{$token}",
            'rotate_interval_seconds' => $rotateSeconds,
            'expired_at' => $expiredAt->toIso8601String(),
        ];
    }

    /**
     * Selesaikan sesi perkuliahan dan tutup absensi kelas.
     */
    public function selesaiSesiPertemuan(int $pertemuanId, ?int $userId = null): ?array
    {
        $pertemuan = Pertemuan::with('kelas.mataKuliah')->find($pertemuanId);
        if (!$pertemuan) {
            return null;
        }

        $oldValues = $pertemuan->getOriginal();
        $alreadySelesai = $pertemuan->status_pertemuan === Pertemuan::STATUS_SELESAI;

        if (!$alreadySelesai) {
            $pertemuan->status_pertemuan = Pertemuan::STATUS_SELESAI;
            $pertemuan->jam_selesai = Carbon::now()->format('H:i');
            $pertemuan->presensi_closed_at = Carbon::now();
            // Tutup presensi: hapus token agar tidak bisa dipakai ulang.
            $pertemuan->token_absensi = null;
            $pertemuan->token_expired_at = null;
            $pertemuan->save();

            try {
                AuditLogService::record(
                    module: 'siakad',
                    action: 'finish_perkuliahan_session',
                    tableName: 'siakad_pertemuan',
                    recordId: $pertemuan->id,
                    oldValues: $oldValues,
                    newValues: $pertemuan->getChanges(),
                    request: request()
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log selesai perkuliahan: ' . $e->getMessage());
            }
        }

        // Rekap akhir presensi
        $hadir = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_HADIR)->count();
        $izin = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_IZIN)->count();
        $sakit = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_SAKIT)->count();
        $alfa = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_ALFA)->count();

        return [
            'id' => $pertemuan->id,
            'kelas_id' => $pertemuan->kelas_id,
            'pertemuan_ke' => $pertemuan->pertemuan_ke,
            'status' => $pertemuan->status_pertemuan,
            'materi' => $pertemuan->materi,
            'jam_mulai' => $pertemuan->jam_mulai,
            'jam_selesai' => $pertemuan->jam_selesai,
            'rekap' => [
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
            ],
        ];
    }

    /**
     * Ambil daftar presensi mahasiswa untuk pertemuan tertentu.
     */
    public function getPresensiPertemuan(int $pertemuanId): ?array
    {
        $pertemuan = Pertemuan::with(['kelas.mataKuliah', 'kelas.ruangan'])->find($pertemuanId);
        if (!$pertemuan) {
            return null;
        }

        $peserta = KrsDetail::with(['krs.mahasiswa'])
            ->where('kelas_id', $pertemuan->kelas_id)
            ->get();

        $absensiMap = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)
            ->get()
            ->keyBy('mahasiswa_id');

        $mahasiswaList = [];
        foreach ($peserta as $p) {
            $m = $p->mahasiswa ?? $p->krs?->mahasiswa;
            if (!$m) continue;

            $absen = $absensiMap->get($m->id);

            $mahasiswaList[] = [
                'mahasiswa_id' => $m->id,
                'nim' => $m->nim ?? '-',
                'nama' => $m->nama_lengkap ?? $m->nama ?? 'Mahasiswa',
                'status' => $absen?->status ?? AbsensiMahasiswa::STATUS_HADIR,
                'catatan' => $absen?->catatan,
                'is_submitted' => (bool) ($absen?->is_submitted ?? false),
            ];
        }

        // Urutkan berdasarkan nama
        usort($mahasiswaList, fn($a, $b) => strcmp($a['nama'], $b['nama']));

        $hadir = collect($mahasiswaList)->where('status', AbsensiMahasiswa::STATUS_HADIR)->count();
        $izin = collect($mahasiswaList)->where('status', AbsensiMahasiswa::STATUS_IZIN)->count();
        $sakit = collect($mahasiswaList)->where('status', AbsensiMahasiswa::STATUS_SAKIT)->count();
        $alfa = collect($mahasiswaList)->where('status', AbsensiMahasiswa::STATUS_ALFA)->count();

        return [
            'pertemuan' => [
                'id' => $pertemuan->id,
                'kelas_id' => $pertemuan->kelas_id,
                'pertemuan_ke' => $pertemuan->pertemuan_ke,
                'materi' => $pertemuan->materi,
                'catatan' => $pertemuan->catatan_pertemuan,
                'tanggal' => $pertemuan->tanggal ? Carbon::parse($pertemuan->tanggal)->format('d M Y') : null,
                'jam_mulai' => $pertemuan->jam_mulai ? substr($pertemuan->jam_mulai, 0, 5) : null,
                'jam_selesai' => $pertemuan->jam_selesai ? substr($pertemuan->jam_selesai, 0, 5) : null,
                'status' => $pertemuan->status_pertemuan,
                'token_absensi' => $pertemuan->token_absensi,
                'is_active' => $pertemuan->status_pertemuan === Pertemuan::STATUS_BERLANGSUNG,
                'nama_mk' => $pertemuan->kelas?->mataKuliah?->nama ?? $pertemuan->kelas?->mataKuliah?->nama_mk ?? '-',
                'nama_kelas' => $pertemuan->kelas?->nama_kelas ?? '-',
                'ruangan' => $pertemuan->kelas?->ruangan?->nama_ruangan ?? 'Ruang Kuliah',
            ],
            'rekap' => [
                'total' => count($mahasiswaList),
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
            ],
            'mahasiswa' => $mahasiswaList,
        ];
    }

    /**
     * Update presensi mahasiswa per orangan atau batch.
     */
    public function updatePresensiMahasiswa(int $pertemuanId, array $items, ?int $userId = null): array
    {
        $pertemuan = Pertemuan::findOrFail($pertemuanId);
        app(PresensiPengajaranService::class)->assertWriteAllowed(
            $pertemuan,
            (int) ($userId ?? auth()->id())
        );

        $updated = 0;

        DB::transaction(function () use ($pertemuanId, $items, &$updated) {
            foreach ($items as $item) {
                $mhsId = $item['mahasiswa_id'] ?? null;
                if (!$mhsId) continue;

                $status = $item['status'] ?? AbsensiMahasiswa::STATUS_HADIR;
                if (!in_array($status, AbsensiMahasiswa::STATUSES, true)) {
                    $status = AbsensiMahasiswa::STATUS_HADIR;
                }

                $absen = AbsensiMahasiswa::firstOrNew([
                    'pertemuan_id' => $pertemuanId,
                    'mahasiswa_id' => $mhsId,
                ]);

                $absen->status = $status;
                $absen->catatan = $item['catatan'] ?? null;
                $absen->is_submitted = true;
                $absen->save();

                $updated++;
            }
        });

        $hadir = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_HADIR)->count();
        $izin = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_IZIN)->count();
        $sakit = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_SAKIT)->count();
        $alfa = AbsensiMahasiswa::where('pertemuan_id', $pertemuanId)->where('status', AbsensiMahasiswa::STATUS_ALFA)->count();

        try {
            AuditLogService::record(
                module: 'siakad',
                action: 'batch_update_attendance',
                tableName: 'siakad_absensi_mahasiswa',
                recordId: $pertemuanId,
                oldValues: null,
                newValues: [
                    'pertemuan_id' => $pertemuanId,
                    'updated_count' => $updated,
                    'items' => $items,
                ],
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log batch absensi: ' . $e->getMessage());
        }

        return [
            'updated_count' => $updated,
            'rekap' => [
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
            ],
        ];
    }

    // =========================================================================
    // MODUL TERPADU MYINU-STAFF (SIPPM, SIKEU, SINAPRA, ARSIP, LMS, SPMB)
    // =========================================================================

    /**
     * Format standard meta array dari paginator.
     */
    public function formatMeta(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Helper pagination untuk Collection generik.
     */
    public function paginateCollection(Collection $items, int $perPage = 15, int $page = 1, ?string $path = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $sliced = $items->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $sliced,
            $items->count(),
            $perPage,
            $page,
            ['path' => $path ?? request()->url(), 'query' => request()->query()]
        );

        return [
            'items' => $sliced->all(),
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SIPPM: Daftar proposal kegiatan penelitian / pengabdian milik dosen.
     */
    public function getSippmProposals(User $user, ?Request $request = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        $query = ProposalKegiatan::with(['skema', 'periode', 'ketuaPegawai', 'anggota'])
            ->where(function ($q) use ($pegawai) {
                $q->where('ketua_pegawai_id', $pegawai->id)
                  ->orWhereHas('anggota', fn($sub) => $sub->where('pegawai_id', $pegawai->id));
            });

        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('rumpun_ilmu', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'title' => $p->judul,
                'subtitle' => ($p->skema?->nama_skema ?? 'Skema Hibah') . ' (' . ($p->rumpun_ilmu ?? 'Umum') . ')',
                'status' => $p->status,
                'date' => $p->created_at?->format('Y-m-d') ?? '',
                'amount' => 'Rp ' . number_format((float) $p->anggaran_diajukan, 0, ',', '.'),
                'kode_proposal' => $p->kode_proposal,
                'skema' => $p->skema?->nama_skema,
                'rumpun_ilmu' => $p->rumpun_ilmu,
                'target_tkt' => $p->target_tkt,
                'file_proposal_url' => $p->file_proposal ? $this->fileStorageService->signedUrl($p->file_proposal) : null,
                'raw' => collect($p->toArray())->except(['file_proposal'])->all(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SIPPM: Ajukan proposal penelitian / pengabdian baru via mobile.
     */
    public function storeSippmProposal(User $user, array $data, $file = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai/dosen.');
        }

        if ($file instanceof UploadedFile) {
            $data['file_proposal'] = $this->fileStorageService->store($file, 'sippm/proposal', private: true);
        }

        if (empty($data['file_proposal'])) {
            throw new \DomainException('Dokumen usulan proposal penelitian/pengabdian wajib dilampirkan.');
        }

        $periodeId = $data['periode_id'] ?? PeriodeHibah::where('is_active', true)->first()?->id;
        if (!$periodeId) {
            throw new \DomainException('Tidak ada periode hibah penelitian/pengabdian yang sedang dibuka saat ini.');
        }

        $year = date('Y');
        $count = ProposalKegiatan::whereYear('created_at', $year)->count() + 1;
        $kodeProposal = 'PRP-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $created = DB::transaction(function () use ($data, $periodeId, $pegawai, $kodeProposal) {
            $p = ProposalKegiatan::create([
                'periode_id' => (int) $periodeId,
                'skema_id' => (int) $data['skema_id'],
                'ketua_pegawai_id' => $pegawai->id,
                'mata_kuliah_id' => $data['mata_kuliah_id'] ?? null,
                'kode_proposal' => $kodeProposal,
                'judul' => $data['judul'],
                'abstrak' => $data['abstrak'],
                'rumpun_ilmu' => $data['rumpun_ilmu'],
                'target_tkt' => $data['target_tkt'] ?? 1,
                'anggaran_diajukan' => $data['anggaran_diajukan'],
                'file_proposal' => $data['file_proposal'],
                'status' => 'draft',
            ]);

            $anggota = AnggotaKegiatan::create([
                'proposal_id' => $p->id,
                'jenis_tim' => 'dosen',
                'pegawai_id' => $pegawai->id,
                'peran_dalam_tim' => 'Ketua Pengusul',
                'tugas_kegiatan' => 'Ketua Pengusul & Penanggung Jawab Riset',
            ]);

            return ['proposal' => $p, 'anggota' => $anggota];
        });

        $proposal = $created['proposal'];
        $anggota = $created['anggota'];

        try {
            AuditLogService::record(
                module: 'SIPPM',
                action: 'create',
                tableName: 'sippm_proposal_kegiatan',
                recordId: $proposal->id,
                oldValues: null,
                newValues: $proposal->only([
                    'periode_id', 'skema_id', 'ketua_pegawai_id', 'kode_proposal',
                    'judul', 'rumpun_ilmu', 'target_tkt', 'anggaran_diajukan', 'status'
                ]),
                request: request()
            );

            AuditLogService::record(
                module: 'SIPPM',
                action: 'create',
                tableName: 'sippm_anggota_kegiatan',
                recordId: $anggota->id,
                oldValues: null,
                newValues: $anggota->only([
                    'proposal_id', 'jenis_tim', 'pegawai_id', 'peran_dalam_tim', 'tugas_kegiatan'
                ]),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log mobile proposal: ' . $e->getMessage());
        }

        return [
            'id' => (string) $proposal->id,
            'title' => $proposal->judul,
            'subtitle' => ($proposal->skema?->nama_skema ?? 'Skema Hibah') . ' (' . ($proposal->rumpun_ilmu ?? 'Umum') . ')',
            'status' => $proposal->status,
            'date' => $proposal->created_at?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => 'Rp ' . number_format((float) $proposal->anggaran_diajukan, 0, ',', '.'),
            'kode_proposal' => $proposal->kode_proposal,
            'raw' => $proposal->load(['skema', 'periode', 'ketuaPegawai'])->toArray(),
        ];
    }

    /**
     * SIPPM: Daftar luaran penelitian & publikasi / HKI milik dosen.
     */
    public function getSippmLuaran(User $user, ?Request $request = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        $jenis = strtolower((string) $request?->query('jenis', 'publikasi'));

        if ($jenis === 'hki') {
            $query = HkiDanBuku::where('pegawai_id', $pegawai->id);
            if ($request?->filled('search')) {
                $search = $request->search;
                $query->where('judul', 'like', "%{$search}%");
            }

            $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

            $items = $paginator->getCollection()->map(function ($hki) {
                return [
                    'id' => 'HKI-' . $hki->id,
                    'title' => $hki->judul,
                    'subtitle' => ($hki->jenis_luaran ?: 'HKI / Buku') . ($hki->nomor_pencatatan_isbn ? ' - ' . $hki->nomor_pencatatan_isbn : ''),
                    'status' => $hki->is_verified_lppm ? 'terverifikasi' : 'draft',
                    'date' => $hki->tgl_terbit_catat ? Carbon::parse($hki->tgl_terbit_catat)->format('Y-m-d') : ($hki->created_at?->format('Y-m-d') ?? ''),
                    'amount' => '',
                    'raw' => $hki->toArray(),
                ];
            })->values()->all();

            return [
                'items' => $items,
                'meta' => $this->formatMeta($paginator),
            ];
        }

        $query = PublikasiIlmiah::where('pegawai_id', $pegawai->id);
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul_artikel', 'like', "%{$search}%")
                  ->orWhere('nama_jurnal_prosiding', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($pub) {
            return [
                'id' => 'PUB-' . $pub->id,
                'title' => $pub->judul_artikel,
                'subtitle' => ($pub->nama_jurnal_prosiding ?: 'Publikasi Ilmiah') . ' (' . ($pub->jenis_publikasi ?: 'Jurnal') . ')',
                'status' => $pub->is_verified_lppm ? 'terverifikasi' : 'draft',
                'date' => $pub->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'raw' => $pub->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SIKEU: Riwayat slip payroll & gaji staf.
     */
    public function getSikeuPayroll(User $user, ?Request $request = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        $query = GajiPegawai::where('pegawai_id', $pegawai->id);
        $paginator = $query->orderBy('periode_bulan_tahun', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($g) {
            return [
                'id' => (string) $g->id,
                'title' => 'Slip Gaji Periode ' . $g->periode_bulan_tahun,
                'subtitle' => 'Gaji Pokok: Rp ' . number_format((float) $g->gaji_pokok, 0, ',', '.') . ' | Potongan: Rp ' . number_format((float) $g->total_potongan, 0, ',', '.'),
                'status' => $g->status_transfer ?: 'terbit',
                'date' => $g->tanggal_transfer ? Carbon::parse($g->tanggal_transfer)->format('Y-m-d') : ($g->created_at?->format('Y-m-d') ?? ''),
                'amount' => 'Rp ' . number_format((float) $g->gaji_bersih, 0, ',', '.'),
                'gaji_bersih' => (string) $g->gaji_bersih,
                'periode' => $g->periode_bulan_tahun,
                'raw' => $g->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SIKEU: Daftar pengajuan keuangan (reimbursement, uang muka, kas bon) milik akun login.
     */
    public function getSikeuPengajuan(User $user, ?Request $request = null): array
    {
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = PengajuanPencairanKas::where('pemohon_id', $user->id);

        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('jenis_pengajuan')) {
            $query->where('jenis_pengajuan', $request->jenis_pengajuan);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul_pengajuan', 'like', "%{$search}%")
                  ->orWhere('nomor_pengajuan', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'title' => $p->judul_pengajuan,
                'subtitle' => ($p->nomor_pengajuan ?: 'Pengajuan') . ($p->deskripsi ? ' - ' . Str::limit($p->deskripsi, 60) : ''),
                'status' => $p->status,
                'date' => $p->created_at?->format('Y-m-d') ?? '',
                'amount' => 'Rp ' . number_format((float) $p->nominal_diajukan, 0, ',', '.'),
                'jenis_pengajuan' => $p->jenis_pengajuan,
                'nomor_pengajuan' => $p->nomor_pengajuan,
                'raw' => $p->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SIKEU: Kirim pengajuan keuangan baru via mobile.
     */
    public function storeSikeuPengajuan(User $user, array $data, $file = null): array
    {
        if ($file instanceof UploadedFile) {
            $data['file_lampiran'] = $this->fileStorageService->store($file, 'sikeu/pengajuan', private: true);
        }

        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data kepegawaian.');
        }

        $unitKasId = $data['unit_kas_id'] ?? null;
        if ($unitKasId) {
            $unitKasQuery = DB::table('sikeu_unit_kas')->where('id', $unitKasId);
            if ($pegawai->unit_kerja_id) {
                $unitKasQuery->where(function ($q) use ($pegawai) {
                    $q->where('unit_kerja_id', $pegawai->unit_kerja_id)
                      ->orWhereNull('unit_kerja_id');
                });
            }
            if (!$unitKasQuery->exists()) {
                throw new \DomainException('Unit kas yang dipilih tidak valid atau di luar cakupan unit kerja Anda.');
            }
        }

        $nomor = 'PK-' . date('Ym') . '-' . str_pad(PengajuanPencairanKas::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT);

        $pengajuan = PengajuanPencairanKas::create([
            'nomor_pengajuan' => $nomor,
            'pemohon_id' => $user->id,
            'unit_kerja_id' => $pegawai->unit_kerja_id,
            'unit_kas_id' => $unitKasId,
            'judul_pengajuan' => $data['judul_pengajuan'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'jenis_pengajuan' => $data['jenis_pengajuan'],
            'nominal_diajukan' => $data['nominal_diajukan'],
            'nama_bank_penerima' => $data['nama_bank_penerima'] ?? null,
            'nomor_rekening_penerima' => $data['nomor_rekening_penerima'] ?? null,
            'nama_rekening_penerima' => $data['nama_rekening_penerima'] ?? null,
            'file_lampiran' => $data['file_lampiran'] ?? null,
            'status' => 'pending_keuangan',
        ]);

        try {
            AuditLogService::record(
                module: 'SIKEU',
                action: 'create',
                tableName: 'sikeu_pengajuan_pencairan_kas',
                recordId: $pengajuan->id,
                oldValues: null,
                newValues: $pengajuan->only([
                    'nomor_pengajuan', 'pemohon_id', 'unit_kerja_id', 'judul_pengajuan',
                    'jenis_pengajuan', 'nominal_diajukan', 'status'
                ]),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log mobile pengajuan: ' . $e->getMessage());
        }

        return [
            'id' => (string) $pengajuan->id,
            'title' => $pengajuan->judul_pengajuan,
            'subtitle' => ($pengajuan->nomor_pengajuan ?: 'Pengajuan') . ($pengajuan->deskripsi ? ' - ' . Str::limit($pengajuan->deskripsi, 60) : ''),
            'status' => $pengajuan->status,
            'date' => $pengajuan->created_at?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => 'Rp ' . number_format((float) $pengajuan->nominal_diajukan, 0, ',', '.'),
            'nomor_pengajuan' => $pengajuan->nomor_pengajuan,
            'raw' => $pengajuan->toArray(),
        ];
    }

    /**
     * SINAPRA: Daftar tiket laporan kerusakan sarana milik akun.
     */
    public function getSinapraTiket(User $user, ?Request $request = null): array
    {
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = MaintenanceLog::with(['aset', 'ruangan', 'teknisi'])
            ->where(function ($q) use ($user) {
                $q->where('pelapor_id', $user->id)
                  ->orWhere('teknisi_id', $user->id);
            });

        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('prioritas')) {
            $query->where('prioritas', $request->prioritas);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi_kerusakan', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($m) {
            $subtitle = '';
            if ($m->ruangan?->nama_ruangan) {
                $subtitle .= 'Ruangan: ' . $m->ruangan->nama_ruangan;
            }
            if ($m->aset?->nama_aset) {
                $subtitle .= ($subtitle ? ' | ' : '') . 'Aset: ' . $m->aset->nama_aset;
            }
            if (empty($subtitle)) {
                $subtitle = $m->deskripsi_kerusakan ? Str::limit($m->deskripsi_kerusakan, 50) : 'Laporan Kerusakan';
            }

            return [
                'id' => (string) $m->id,
                'title' => $m->judul,
                'subtitle' => $subtitle,
                'status' => $m->status,
                'date' => $m->tanggal_lapor?->format('Y-m-d') ?? ($m->created_at?->format('Y-m-d') ?? ''),
                'amount' => (float) $m->biaya > 0 ? 'Rp ' . number_format((float) $m->biaya, 0, ',', '.') : '',
                'prioritas' => $m->prioritas,
                'foto_url' => $m->foto_path ? $this->fileStorageService->signedUrl($m->foto_path) : null,
                'raw' => $m->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SINAPRA: Kirim laporan kerusakan sarana baru via mobile.
     */
    public function storeSinapraTiket(User $user, array $data, $foto = null): array
    {
        if (!empty($data['aset_id'])) {
            $asetExists = DB::table('sinapra_aset')->where('id', $data['aset_id'])->exists();
            if (!$asetExists) {
                throw new \DomainException('Aset yang dilaporkan tidak ditemukan.');
            }
        }
        if (!empty($data['ruangan_id'])) {
            $ruanganExists = DB::table('sinapra_ruangan')->where('id', $data['ruangan_id'])->exists();
            if (!$ruanganExists) {
                throw new \DomainException('Ruangan yang dilaporkan tidak ditemukan.');
            }
        }

        $fotoPath = null;
        if ($foto instanceof UploadedFile) {
            $fotoPath = $this->fileStorageService->store($foto, 'sinapra/maintenance', private: true);
        }

        $log = $this->maintenanceService->createMaintenanceLog([
            'pelapor_id' => $user->id,
            'aset_id' => $data['aset_id'] ?? null,
            'ruangan_id' => $data['ruangan_id'] ?? null,
            'judul' => $data['judul'],
            'deskripsi_kerusakan' => $data['deskripsi_kerusakan'],
            'foto_path' => $fotoPath,
            'prioritas' => $data['prioritas'] ?? 'sedang',
            'tanggal_lapor' => now()->toDateString(),
            'status' => 'dilaporkan',
        ]);

        try {
            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'sinapra_maintenance_log',
                recordId: $log->id,
                oldValues: null,
                newValues: $log->only([
                    'pelapor_id', 'aset_id', 'ruangan_id', 'judul',
                    'deskripsi_kerusakan', 'prioritas', 'tanggal_lapor', 'status'
                ]),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log tiket sarana: ' . $e->getMessage());
        }

        return [
            'id' => (string) $log->id,
            'title' => $log->judul,
            'subtitle' => $log->deskripsi_kerusakan ? Str::limit($log->deskripsi_kerusakan, 60) : 'Laporan Kerusakan',
            'status' => $log->status,
            'date' => $log->tanggal_lapor?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => '',
            'prioritas' => $log->prioritas,
            'foto_url' => $log->foto_path ? $this->fileStorageService->signedUrl($log->foto_path) : null,
            'raw' => $log->load(['aset', 'ruangan', 'teknisi'])->toArray(),
        ];
    }

    /**
     * SINAPRA: Daftar peminjaman ruangan / fasilitas milik akun.
     */
    public function getSinapraPeminjaman(User $user, ?Request $request = null): array
    {
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $jenis = strtolower((string) $request?->query('jenis', 'ruangan'));

        if ($jenis === 'aset') {
            $query = PeminjamanAset::with('aset')->where('user_id', $user->id);
            if ($request?->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request?->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('keperluan', 'like', "%{$search}%")
                      ->orWhereHas('aset', fn($sub) => $sub->where('nama_aset', 'like', "%{$search}%"));
                });
            }

            $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

            $items = $paginator->getCollection()->map(function ($a) {
                return [
                    'id' => 'ASET-' . $a->id,
                    'title' => $a->aset?->nama_aset ?? 'Peminjaman Aset',
                    'subtitle' => $a->keperluan ?: 'Peminjaman Aset Sarana',
                    'status' => $a->status,
                    'date' => $a->tanggal_pinjam?->format('Y-m-d') ?? ($a->created_at?->format('Y-m-d') ?? ''),
                    'amount' => '',
                    'raw' => $a->toArray(),
                ];
            })->values()->all();

            return [
                'items' => $items,
                'meta' => $this->formatMeta($paginator),
            ];
        }

        $query = PeminjamanRuangan::with('ruangan')->where('user_id', $user->id);
        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keperluan', 'like', "%{$search}%")
                  ->orWhereHas('ruangan', fn($sub) => $sub->where('nama_ruangan', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($r) {
            return [
                'id' => 'RUANG-' . $r->id,
                'title' => $r->ruangan?->nama_ruangan ?? 'Peminjaman Ruangan',
                'subtitle' => $r->keperluan ?: 'Peminjaman Ruangan',
                'status' => $r->status,
                'date' => $r->tanggal?->format('Y-m-d') ?? ($r->created_at?->format('Y-m-d') ?? ''),
                'amount' => '',
                'raw' => $r->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * ARSIP: Dokumen E-File kepegawaian pribadi staf.
     */
    public function getArsipEfile(User $user, ?Request $request = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        $query = DokumenPegawai::where('pegawai_id', $pegawai->id);
        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($d) {
            return [
                'id' => (string) $d->id,
                'title' => $d->nama_dokumen,
                'subtitle' => $d->jenis_dokumen . ($d->file_size ? ' (' . round($d->file_size / 1024, 1) . ' KB)' : ''),
                'status' => $d->status_verifikasi ?: 'tersimpan',
                'date' => $d->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'file_url' => $d->file_path ? $this->fileStorageService->signedUrl($d->file_path) : null,
                'raw' => $d->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * ARSIP: Dokumen persuratan resmi terkait staf.
     */
    public function getArsipSurat(User $user, ?Request $request = null): array
    {
        $pegawai = $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
        $pegawaiId = $pegawai?->id;
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $jenis = strtolower((string) $request?->query('jenis', 'surat'));

        if ($jenis === 'tugas' && $pegawaiId) {
            $query = SuratTugas::where('pegawai_id', $pegawaiId);
            if ($request?->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_kegiatan', 'like', "%{$search}%")
                      ->orWhere('nomor_surat', 'like', "%{$search}%")
                      ->orWhere('tempat_tujuan', 'like', "%{$search}%");
                });
            }

            $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

            $items = $paginator->getCollection()->map(function ($t) {
                return [
                    'id' => 'TGS-' . $t->id,
                    'title' => $t->nama_kegiatan ?: 'Surat Tugas',
                    'subtitle' => ($t->nomor_surat ?: 'Tugas') . ($t->tempat_tujuan ? ' (' . $t->tempat_tujuan . ')' : ''),
                    'status' => $t->status ?: 'aktif',
                    'date' => $t->tanggal_mulai?->format('Y-m-d') ?? ($t->created_at?->format('Y-m-d') ?? ''),
                    'amount' => '',
                    'raw' => $t->toArray(),
                ];
            })->values()->all();

            return [
                'items' => $items,
                'meta' => $this->formatMeta($paginator),
            ];
        }

        $query = NomorSurat::query()
            ->where(function ($q) use ($user, $pegawaiId) {
                $q->where('created_by', $user->id);
                if ($pegawaiId) {
                    $q->orWhere('pegawai_id', $pegawaiId);
                }
            });

        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('perihal', 'like', "%{$search}%")
                  ->orWhere('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($s) {
            return [
                'id' => 'SRT-' . $s->id,
                'title' => $s->perihal ?: ($s->nomor_surat ?: 'Surat Resmi'),
                'subtitle' => ($s->nomor_surat ?: 'Arsip') . ($s->tujuan ? ' | Tujuan: ' . $s->tujuan : ''),
                'status' => $s->status ?: 'terbit',
                'date' => $s->tanggal_surat?->format('Y-m-d') ?? ($s->created_at?->format('Y-m-d') ?? ''),
                'amount' => '',
                'raw' => $s->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * LMS: Daftar bahan ajar & RPS kelas yang diampu dosen.
     */
    public function getLmsBahanAjar(User $user, ?Request $request = null): array
    {
        $dosen = $this->resolveDosen($user->id);
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$dosen) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        $classes = Kelas::where(function ($q) use ($dosen) {
            $q->where('dosen_id', $dosen->id)
              ->orWhereHas('dosenPengampu', fn($dp) => $dp->where('dosen_id', $dosen->id));
        })->pluck('id');

        $query = MateriPertemuan::with(['pertemuan.kelas.mataKuliah', 'files'])
            ->whereHas('pertemuan', fn($p) => $p->whereIn('kelas_id', $classes));

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($m) {
            $mataKuliah = $m->pertemuan?->kelas?->mataKuliah?->nama_mata_kuliah ?? 'Materi Perkuliahan';
            $pertemuanKe = $m->pertemuan?->pertemuan_ke ? ' (Pertemuan ke-' . $m->pertemuan->pertemuan_ke . ')' : '';

            return [
                'id' => (string) $m->id,
                'title' => $m->judul,
                'subtitle' => $mataKuliah . $pertemuanKe,
                'status' => $m->is_published ? 'published' : 'draft',
                'date' => $m->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'raw' => $m->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * LMS: Pengumuman kelas / notifikasi akademik dosen.
     */
    public function getLmsPengumuman(User $user, ?Request $request = null): array
    {
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = LmsNotifikasi::where('user_id', $user->id);
        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($n) {
            return [
                'id' => (string) $n->id,
                'title' => $n->judul,
                'subtitle' => $n->pesan,
                'status' => $n->is_read ? 'dibaca' : 'baru',
                'date' => $n->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'raw' => $n->toArray(),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'meta' => $this->formatMeta($paginator),
        ];
    }

    /**
     * SPMB: Data kode referral, statistik, dan daftar calon mahasiswa binaan.
     */
    public function getSpmbReferral(User $user, ?Request $request = null): array
    {
        $code = $user->referral_code;
        $stats = $this->spmbReferralService->statsForUser($user);
        $shareUrl = $this->getSpmbPublicBaseUrl() . '/spmb/daftar?ref=' . $code;
        $perPage = min(100, max(1, (int) ($request?->input('per_page') ?? 15)));
        $page = max(1, (int) ($request?->input('page') ?? 1));

        $usages = ReferralUsage::with('pendaftar.prodiPilihan1')
            ->where('referrer_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $items = collect();

        // Kartu Utama Referral: Selalu di posisi pertama agar copy button mobile langsung membaca kodenya
        $items->push([
            'id' => (string) $user->id,
            'title' => 'Kode Referral: ' . $code,
            'subtitle' => 'Bagikan kode atau link referral kepada calon mahasiswa baru',
            'status' => 'aktif',
            'date' => now()->format('Y-m-d'),
            'amount' => 'Total: ' . ($stats['total'] ?? 0) . ' pendaftar',
            'referral_code' => $code,
            'kode_referral' => $code,
            'share_url' => $shareUrl,
            'stats' => $stats,
            'raw' => [
                'referral_code' => $code,
                'kode_referral' => $code,
                'share_url' => $shareUrl,
                'stats' => $stats,
            ],
        ]);

        foreach ($usages as $u) {
            $namaPendaftar = $u->pendaftar?->nama ?? ('Pendaftar #' . $u->id);
            $prodi = $u->pendaftar?->prodiPilihan1?->nama_prodi ?? 'Prodi Pilihan';

            $items->push([
                'id' => (string) $u->id,
                'title' => $namaPendaftar,
                'subtitle' => $prodi . ' (Kode: ' . $u->referral_code . ')',
                'status' => $u->status,
                'date' => $u->created_at?->format('Y-m-d') ?? '',
                'amount' => (float) $u->reward_amount > 0 ? 'Rp ' . number_format((float) $u->reward_amount, 0, ',', '.') : '',
                'referral_code' => $code,
                'kode_referral' => $code,
                'raw' => $u->toArray(),
            ]);
        }

        return $this->paginateCollection($items, $perPage, $page);
    }

    /**
     * SPMB: Buat / regenerasi link referral eksternal & QR payload.
     */
    public function generateSpmbReferral(User $user): array
    {
        $code = $user->referral_code;
        $shareUrl = $this->getSpmbPublicBaseUrl() . '/spmb/daftar?ref=' . $code;

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: 'core_users',
                recordId: $user->id,
                oldValues: null,
                newValues: ['referral_code' => $code, 'action' => 'generate_referral_link'],
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log SPMB referral: ' . $e->getMessage());
        }

        return [
            'referral_code' => $code,
            'kode_referral' => $code,
            'share_url' => $shareUrl,
            'qr_payload' => $shareUrl,
            'stats' => $this->spmbReferralService->statsForUser($user),
        ];
    }

    /**
     * Resolve base URL publik untuk tautan registrasi SPMB dari environment RAG.
     */
    protected function getSpmbPublicBaseUrl(): string
    {
        $baseUrl = config('spmb.public_url')
            ?: config('app.frontend_url')
            ?: config('app.url', 'http://localhost:3000');

        return rtrim((string) $baseUrl, '/');
    }
}
