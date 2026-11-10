<?php

namespace App\Services\Mobile;

use App\Models\Lms\KelasLmsSetting;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\TahunAkademik;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\MahasiswaBeasiswa;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Services\Siakad\ForumService;
use App\Services\Siakad\LmsService;
use App\Services\Siakad\QuizService;
use Illuminate\Auth\Access\AuthorizationException;

class MobileMahasiswaService
{
    public function __construct(
        private LmsService $lmsService,
        private QuizService $quizService,
        private ForumService $forumService,
    ) {}

    /**
     * Pastikan pemanggil adalah Mahasiswa aktif yang memiliki profil Mahasiswa terhubung ke user_id.
     * Sesuai aturan RBAC (.agents/rules/module-role-rbac.md), Super Admin / Dosen tanpa profil
     * Mahasiswa ditolak dengan 403.
     */
    public function resolveAuthenticatedMahasiswa(int $userId): Mahasiswa
    {
        $mahasiswa = Mahasiswa::with(['programStudi.fakultas', 'dosenWali'])
            ->where('user_id', $userId)
            ->first();

        if (! $mahasiswa) {
            throw new AuthorizationException('Endpoint aplikasi mobile mahasiswa hanya dapat diakses oleh akun dengan profil Mahasiswa aktif.');
        }

        return $mahasiswa;
    }

    /**
     * Resolve tahun akademik efektif (default ke periode aktif `is_active = true`).
     */
    public function resolveEffectiveTahunAkademik(?int $tahunAkademikId = null): ?TahunAkademik
    {
        if ($tahunAkademikId) {
            return TahunAkademik::find($tahunAkademikId);
        }

        return TahunAkademik::where('is_active', true)->first()
            ?? TahunAkademik::orderByDesc('id')->first();
    }

    /**
     * Daftar seluruh periode akademik beserta penanda periode aktif.
     */
    public function listPeriodeAkademik(): array
    {
        $items = TahunAkademik::orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TahunAkademik $ta) => [
                'id' => $ta->id,
                'kode' => $ta->kode,
                'nama' => $ta->nama,
                'tahun_mulai' => $ta->tahun_mulai,
                'tahun_selesai' => $ta->tahun_selesai,
                'is_active' => (bool) $ta->is_active,
            ])
            ->values()
            ->all();

        $aktif = collect($items)->firstWhere('is_active', true);

        return [
            'periode_aktif' => $aktif,
            'daftar_periode' => $items,
        ];
    }

    /**
     * Ringkasan terpadu Beranda (Home) Aplikasi Mobile Mahasiswa:
     * - Profil & Kartu Mahasiswa Digital (KTM)
     * - Periode Akademik Aktif
     * - Banner Sesi Presensi Aktif (QR/Token berputar 30 detik)
     * - Jadwal Kuliah Hari Ini / Kelas Aktif
     * - Ringkasan LMS (Tugas belum dikumpul, Tryout aktif, Notifikasi)
     * - Ringkasan Keuangan SIKEU (Tagihan belum lunas & potongan beasiswa)
     */
    public function getHomeOverview(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);
        $periode = $this->resolveEffectiveTahunAkademik($tahunAkademikId);
        $effectiveTaId = $periode?->id;

        $lmsOverview = $this->lmsService->dashboardOverview($userId, $effectiveTaId);
        $sesiPresensiAktif = $this->lmsService->listSesiPresensiAktifMahasiswa($userId, $effectiveTaId);
        $jadwal = $this->getJadwalKuliah($userId, $effectiveTaId);
        $sikeuRingkasan = $this->getSikeuRingkasan($userId);

        return [
            'mahasiswa' => $this->formatProfilMahasiswa($mahasiswa),
            'periode_aktif' => $periode ? [
                'id' => $periode->id,
                'kode' => $periode->kode,
                'nama' => $periode->nama,
                'tahun_mulai' => $periode->tahun_mulai,
                'tahun_selesai' => $periode->tahun_selesai,
                'is_active' => (bool) $periode->is_active,
            ] : null,
            'sesi_presensi_aktif' => $sesiPresensiAktif,
            'lms_ringkasan' => [
                'ringkasan' => $lmsOverview['ringkasan'] ?? [],
                'tambahan' => $lmsOverview['tambahan'] ?? [],
            ],
            'jadwal_semester' => $jadwal['jadwal'],
            'sikeu_ringkasan' => $sikeuRingkasan,
        ];
    }

    /**
     * SIAKAD: Profil & Kartu Mahasiswa Digital (KTM).
     */
    public function getProfilSiakad(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        return $this->formatProfilMahasiswa($mahasiswa);
    }

    private function formatProfilMahasiswa(Mahasiswa $mhs): array
    {
        return [
            'id' => $mhs->id,
            'user_id' => $mhs->user_id,
            'nim' => $mhs->nim,
            'nama_lengkap' => $mhs->nama_lengkap,
            'email' => $mhs->email,
            'telepon' => $mhs->telepon,
            'angkatan' => $mhs->angkatan,
            'jalur_masuk' => $mhs->jalur_masuk,
            'kelas' => $mhs->kelas,
            'kelompok_ukt' => $mhs->kelompok_ukt,
            'status' => $mhs->status,
            'ipk' => (float) $mhs->ipk,
            'program_studi' => $mhs->programStudi ? [
                'id' => $mhs->programStudi->id,
                'kode_prodi' => $mhs->programStudi->kode_prodi ?? null,
                'nama' => $mhs->programStudi->nama ?? $mhs->programStudi->nama_prodi ?? null,
                'jenjang' => $mhs->programStudi->jenjang ?? null,
            ] : null,
            'dosen_wali' => $mhs->dosenWali ? [
                'id' => $mhs->dosenWali->id,
                'nidn' => $mhs->dosenWali->nidn,
                'nama_lengkap' => $mhs->dosenWali->nama_lengkap,
            ] : null,
            'ktm_qr_payload' => "RAG-KTM:{$mhs->nim}:{$mhs->id}",
        ];
    }

    /**
     * SIAKAD: Jadwal kuliah mahasiswa pada periode aktif (atau periode yang dipilih).
     */
    public function getJadwalKuliah(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);
        $periode = $this->resolveEffectiveTahunAkademik($tahunAkademikId);

        $details = KrsDetail::with([
            'kelas.mataKuliah',
            'kelas.ruangan',
            'kelas.dosenPengampu.dosen',
            'kelas.tahunAkademik',
        ])
            ->whereHas('krs', function ($q) use ($mahasiswa, $periode) {
                $q->where('mahasiswa_id', $mahasiswa->id)
                    ->whereIn('status', ['disetujui', 'diajukan']);
                if ($periode) {
                    $q->where('tahun_akademik_id', $periode->id);
                }
            })
            ->get();

        $jadwal = $details->map(function (KrsDetail $d) {
            $kelas = $d->kelas;
            $mk = $kelas?->mataKuliah;

            return [
                'krs_detail_id' => $d->id,
                'kelas_id' => $kelas?->id,
                'kode_kelas' => $kelas?->kode_kelas,
                'nama_kelas' => $kelas?->nama_kelas,
                'hari' => $kelas?->hari,
                'jam_mulai' => $kelas?->jam_mulai,
                'jam_selesai' => $kelas?->jam_selesai,
                'ruangan' => $kelas?->ruangan ? [
                    'id' => $kelas->ruangan->id,
                    'kode' => $kelas->ruangan->kode_ruang ?? $kelas->ruangan->kode ?? null,
                    'nama' => $kelas->ruangan->nama_ruang ?? $kelas->ruangan->nama_ruangan ?? $kelas->ruangan->nama ?? null,
                ] : null,
                'mata_kuliah' => $mk ? [
                    'id' => $mk->id,
                    'kode_mk' => $mk->kode_mk,
                    'nama' => $mk->nama,
                    'sks' => (int) ($mk->total_sks ?? 0),
                ] : null,
                'dosen_pengampu' => ($kelas?->dosenPengampu ?? collect())->map(fn ($dp) => [
                    'dosen_id' => $dp->dosen_id,
                    'nama_lengkap' => $dp->dosen?->nama_lengkap,
                    'peran' => $dp->peran,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'periode' => $periode ? [
                'id' => $periode->id,
                'kode' => $periode->kode,
                'nama' => $periode->nama,
                'is_active' => (bool) $periode->is_active,
            ] : null,
            'total_kelas' => count($jadwal),
            'jadwal' => $jadwal,
        ];
    }

    /**
     * SIAKAD: Data KRS mahasiswa pada periode aktif (atau periode yang dipilih).
     */
    public function getKrsMahasiswa(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);
        $periode = $this->resolveEffectiveTahunAkademik($tahunAkademikId);

        $krs = Krs::with([
            'tahunAkademik',
            'dosenPembimbing',
            'krsDetails.kelas.mataKuliah',
            'krsDetails.kelas.ruangan',
            'krsDetails.kelas.dosenPengampu.dosen',
            'krsDetails.nilaiMahasiswa',
        ])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($periode, fn ($q) => $q->where('tahun_akademik_id', $periode->id))
            ->latest('id')
            ->first();

        if (! $krs) {
            return [
                'periode' => $periode ? [
                    'id' => $periode->id,
                    'kode' => $periode->kode,
                    'nama' => $periode->nama,
                    'is_active' => (bool) $periode->is_active,
                ] : null,
                'krs' => null,
                'total_sks' => 0,
                'mata_kuliah_diambil' => [],
            ];
        }

        $items = $krs->krsDetails->map(function (KrsDetail $d) {
            $kelas = $d->kelas;
            $mk = $kelas?->mataKuliah;
            $nilai = $d->nilaiMahasiswa;

            return [
                'krs_detail_id' => $d->id,
                'kelas_id' => $kelas?->id,
                'kode_kelas' => $kelas?->kode_kelas,
                'nama_kelas' => $kelas?->nama_kelas,
                'hari' => $kelas?->hari,
                'jam_mulai' => $kelas?->jam_mulai,
                'jam_selesai' => $kelas?->jam_selesai,
                'mata_kuliah' => $mk ? [
                    'id' => $mk->id,
                    'kode_mk' => $mk->kode_mk,
                    'nama' => $mk->nama,
                    'sks' => (int) ($mk->total_sks ?? 0),
                    'semester' => $mk->semester ?? $mk->semester_anjuran ?? null,
                ] : null,
                'nilai' => $nilai ? [
                    'nilai_akhir' => $nilai->nilai_akhir !== null ? (float) $nilai->nilai_akhir : null,
                    'nilai_huruf' => $nilai->nilai_huruf,
                    'bobot_mutu' => $nilai->bobot_mutu !== null ? (float) $nilai->bobot_mutu : null,
                    'is_final' => (bool) $nilai->is_final,
                ] : null,
            ];
        })->values();

        return [
            'periode' => $krs->tahunAkademik ? [
                'id' => $krs->tahunAkademik->id,
                'kode' => $krs->tahunAkademik->kode,
                'nama' => $krs->tahunAkademik->nama,
                'is_active' => (bool) $krs->tahunAkademik->is_active,
            ] : null,
            'krs' => [
                'id' => $krs->id,
                'status' => $krs->status,
                'total_sks' => (int) ($krs->total_sks_diambil ?? $items->sum(fn ($i) => $i['mata_kuliah']['sks'] ?? 0)),
                'locked_by_keuangan' => (bool) $krs->locked_by_keuangan,
                'disetujui_at' => $krs->disetujui_at ? $krs->disetujui_at->toIso8601String() : null,
            ],
            'total_sks' => (int) $items->sum(fn ($i) => $i['mata_kuliah']['sks'] ?? 0),
            'mata_kuliah_diambil' => $items->all(),
        ];
    }

    /**
     * SIAKAD: Kartu Hasil Studi (KHS) mahasiswa per periode atau seluruh semester.
     */
    public function getKhsMahasiswa(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $khsList = Khs::with('tahunAkademik')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($tahunAkademikId, fn ($q) => $q->where('tahun_akademik_id', $tahunAkademikId))
            ->orderByDesc('tahun_akademik_id')
            ->orderByDesc('id')
            ->get();

        $daftarKhs = $khsList->map(function (Khs $khs) use ($mahasiswa) {
            $nilaiSemester = NilaiMahasiswa::with(['krsDetail.kelas.mataKuliah'])
                ->whereHas('krsDetail.krs', function ($q) use ($mahasiswa, $khs) {
                    $q->where('mahasiswa_id', $mahasiswa->id)
                        ->where('tahun_akademik_id', $khs->tahun_akademik_id);
                })
                ->get()
                ->map(function (NilaiMahasiswa $n) {
                    $mk = $n->krsDetail?->kelas?->mataKuliah;
                    $sks = (int) ($mk?->total_sks ?? 0);
                    $mutu = (float) ($n->bobot_mutu ?? 0);

                    return [
                        'kelas_id' => $n->krsDetail?->kelas_id,
                        'kode_mk' => $mk?->kode_mk,
                        'nama_mk' => $mk?->nama,
                        'sks' => $sks,
                        'nilai_akhir' => $n->nilai_akhir !== null ? (float) $n->nilai_akhir : null,
                        'nilai_huruf' => $n->nilai_huruf,
                        'bobot_mutu' => $mutu,
                        'sks_x_mutu' => round($sks * $mutu, 2),
                        'is_final' => (bool) $n->is_final,
                    ];
                })->values()->all();

            return [
                'id' => $khs->id,
                'tahun_akademik_id' => $khs->tahun_akademik_id,
                'periode' => $khs->tahunAkademik ? [
                    'id' => $khs->tahunAkademik->id,
                    'kode' => $khs->tahunAkademik->kode,
                    'nama' => $khs->tahunAkademik->nama,
                    'is_active' => (bool) $khs->tahunAkademik->is_active,
                ] : null,
                'ips' => (float) $khs->ips,
                'ipk' => (float) $khs->ipk,
                'total_sks_semester' => (int) $khs->total_sks_semester,
                'sks_kumulatif' => (int) $khs->sks_kumulatif,
                'is_locked' => (bool) $khs->is_locked,
                'nilai_mata_kuliah' => $nilaiSemester,
            ];
        })->values()->all();

        return [
            'ipk_terakhir' => (float) $mahasiswa->ipk,
            'daftar_khs' => $daftarKhs,
        ];
    }

    /**
     * SIAKAD: Transkrip nilai sementara mahasiswa.
     */
    public function getTranskripMahasiswa(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $nilaiList = NilaiMahasiswa::with([
            'krsDetail.krs.tahunAkademik',
            'krsDetail.kelas.mataKuliah',
        ])
            ->whereHas('krsDetail.krs', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->where('is_final', true)
            ->get();

        $items = [];
        $totalSksDiambil = 0;
        $totalSksLulus = 0;
        $totalBobotMutu = 0.0;

        foreach ($nilaiList as $n) {
            $mk = $n->krsDetail?->kelas?->mataKuliah;
            $sks = (int) ($mk?->total_sks ?? 0);
            $mutu = (float) ($n->bobot_mutu ?? 0);
            $huruf = strtoupper(trim((string) $n->nilai_huruf));

            $totalSksDiambil += $sks;
            if (! in_array($huruf, ['E', ''], true)) {
                $totalSksLulus += $sks;
            }
            $totalBobotMutu += ($sks * $mutu);

            $items[] = [
                'kode_mk' => $mk?->kode_mk,
                'nama_mk' => $mk?->nama,
                'semester_mk' => $mk?->semester ?? $mk?->semester_anjuran,
                'sks' => $sks,
                'nilai_akhir' => $n->nilai_akhir !== null ? (float) $n->nilai_akhir : null,
                'nilai_huruf' => $huruf ?: null,
                'bobot_mutu' => $mutu,
                'mutu_x_sks' => round($sks * $mutu, 2),
                'tahun_akademik' => $n->krsDetail?->krs?->tahunAkademik?->nama,
            ];
        }

        $ipk = $totalSksDiambil > 0 ? round($totalBobotMutu / $totalSksDiambil, 2) : (float) $mahasiswa->ipk;

        return [
            'mahasiswa' => $this->formatProfilMahasiswa($mahasiswa),
            'ringkasan' => [
                'total_sks_diambil' => $totalSksDiambil,
                'total_sks_lulus' => $totalSksLulus,
                'total_mutu' => round($totalBobotMutu, 2),
                'ipk' => $ipk,
            ],
            'mata_kuliah' => $items,
        ];
    }

    /**
     * PRESENSI: Rekap kehadiran seluruh kelas yang diambil mahasiswa pada periode aktif/tertentu.
     */
    public function getRekapPresensiMahasiswa(int $userId, ?int $tahunAkademikId = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);
        $periode = $this->resolveEffectiveTahunAkademik($tahunAkademikId);

        $krsDetails = KrsDetail::with(['kelas.mataKuliah', 'kelas.lmsSetting'])
            ->whereHas('krs', function ($q) use ($mahasiswa, $periode) {
                $q->where('mahasiswa_id', $mahasiswa->id)
                    ->whereIn('status', ['disetujui', 'diajukan']);
                if ($periode) {
                    $q->where('tahun_akademik_id', $periode->id);
                }
            })
            ->get();

        $rekapKelas = $krsDetails->map(function (KrsDetail $d) use ($mahasiswa) {
            $kelas = $d->kelas;
            if (! $kelas) {
                return null;
            }
            $pertemuanIds = Pertemuan::where('kelas_id', $kelas->id)->pluck('id');
            $totalPertemuanTerjadwal = $pertemuanIds->count();
            $batasMinPersen = (int) ($kelas->lmsSetting?->batas_min_hadir_persen ?? KelasLmsSetting::defaultSetting($kelas->id)['batas_min_hadir_persen']);

            $absRows = AbsensiMahasiswa::whereIn('pertemuan_id', $pertemuanIds)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->get();

            $hadir = $absRows->where('status', 'hadir')->count();
            $sakit = $absRows->where('status', 'sakit')->count();
            $izin = $absRows->where('status', 'izin')->count();
            $alfa = $absRows->where('status', 'alfa')->count();

            $persen = $totalPertemuanTerjadwal > 0
                ? round((($hadir + $sakit + $izin) / $totalPertemuanTerjadwal) * 100, 1)
                : 0.0;

            return [
                'kelas_id' => $kelas->id,
                'kode_kelas' => $kelas->kode_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'mata_kuliah' => $kelas->mataKuliah ? [
                    'id' => $kelas->mataKuliah->id,
                    'kode_mk' => $kelas->mataKuliah->kode_mk,
                    'nama' => $kelas->mataKuliah->nama,
                ] : null,
                'total_pertemuan' => $totalPertemuanTerjadwal,
                'hadir' => $hadir,
                'sakit' => $sakit,
                'izin' => $izin,
                'alfa' => $alfa,
                'persen_kehadiran' => $persen,
                'batas_min_hadir_persen' => $batasMinPersen,
                'memenuhi_syarat_ujian' => $persen >= $batasMinPersen,
            ];
        })->filter()->values()->all();

        return [
            'periode' => $periode ? [
                'id' => $periode->id,
                'kode' => $periode->kode,
                'nama' => $periode->nama,
                'is_active' => (bool) $periode->is_active,
            ] : null,
            'rekap_kelas' => $rekapKelas,
        ];
    }

    /**
     * SIKEU: Ringkasan Keuangan Mahasiswa (Tagihan, Potongan Beasiswa, Terbayar, Sisa Tunggakan).
     */
    public function getSikeuRingkasan(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $tagihans = TagihanMahasiswa::where('mahasiswa_id', $mahasiswa->id)->get();

        $totalTagihan = (float) $tagihans->sum('total_tagihan');
        $totalPotonganBeasiswa = (float) $tagihans->sum('total_potongan');
        $totalDenda = (float) $tagihans->sum('total_denda');
        $totalTerbayar = (float) $tagihans->sum('total_bayar');

        $totalSisaBayar = (float) $tagihans->sum(function ($t) {
            return max(0, ($t->total_tagihan + $t->total_denda - $t->total_potongan) - $t->total_bayar);
        });

        $tagihanBelumLunasCount = $tagihans->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi'])->count();
        $dispensasiAktifCount = DispensasiTagihan::where('mahasiswa_id', $mahasiswa->id)
            ->berlaku()
            ->count();

        return [
            'total_tagihan' => $totalTagihan,
            'total_potongan_beasiswa' => $totalPotonganBeasiswa,
            'total_denda' => $totalDenda,
            'total_terbayar' => $totalTerbayar,
            'total_sisa_bayar' => $totalSisaBayar,
            'jumlah_tagihan_belum_lunas' => $tagihanBelumLunasCount,
            'memiliki_dispensasi_aktif' => $dispensasiAktifCount > 0,
            'eligible_krs_dan_ujian' => $totalSisaBayar <= 0 || $dispensasiAktifCount > 0,
        ];
    }

    /**
     * SIKEU: Daftar Tagihan & Invoice Mahasiswa beserta rincian komponen biaya & potongan beasiswa.
     */
    public function getSikeuTagihan(int $userId, ?string $status = null): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $query = TagihanMahasiswa::with([
            'details.masterBiaya',
            'virtualAccount',
            'dispensasis',
            'tipeTagihanMahasiswa',
        ])->where('mahasiswa_id', $mahasiswa->id);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('id')->get()->map(function (TagihanMahasiswa $t) {
            $sisa = max(0, ($t->total_tagihan + $t->total_denda - $t->total_potongan) - $t->total_bayar);

            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'tahun_akademik_id' => $t->tahun_akademik_id,
                'catatan' => $t->catatan_approval,
                'total_tagihan' => (float) $t->total_tagihan,
                'total_potongan_beasiswa' => (float) $t->total_potongan,
                'total_denda' => (float) $t->total_denda,
                'total_bayar' => (float) $t->total_bayar,
                'sisa_bayar' => (float) $sisa,
                'status' => $t->status,
                'jatuh_tempo' => $t->jatuh_tempo ? (is_object($t->jatuh_tempo) ? $t->jatuh_tempo->format('Y-m-d') : (string) $t->jatuh_tempo) : null,
                'virtual_account' => $t->virtualAccount ? [
                    'va_number' => $t->virtualAccount->va_number,
                    'bank_nama' => $t->virtualAccount->bank_nama ?? 'Bank Mitra Kampus',
                ] : null,
                'rincian_komponen' => $t->details->map(fn ($d) => [
                    'id' => $d->id,
                    'nama_biaya' => $d->masterBiaya->nama ?? 'Biaya Pendidikan',
                    'keterangan' => $d->keterangan ?? ($d->masterBiaya->nama ?? 'Biaya Pendidikan'),
                    'nominal' => (float) $d->nominal,
                    'potongan' => (float) $d->potongan,
                    'nominal_bersih' => (float) $d->nominal_bersih,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'ringkasan' => $this->getSikeuRingkasan($userId),
            'tagihan' => $items,
        ];
    }

    /**
     * SIKEU: Riwayat Pembayaran Mahasiswa.
     */
    public function getSikeuPembayaran(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $tagihanIds = TagihanMahasiswa::where('mahasiswa_id', $mahasiswa->id)->pluck('id');

        $pembayarans = Pembayaran::with('tagihan')
            ->whereIn('tagihan_id', $tagihanIds)
            ->orderByDesc('waktu_bayar')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Pembayaran $p) => [
                'id' => $p->id,
                'tagihan_id' => $p->tagihan_id,
                'nomor_tagihan' => $p->tagihan?->nomor_tagihan,
                'kode_transaksi' => $p->kode_transaksi,
                'jumlah_bayar' => (float) $p->jumlah_bayar,
                'metode_bayar' => $p->metode_bayar,
                'channel_bayar' => $p->channel_bayar,
                'status' => $p->status,
                'waktu_bayar' => $p->waktu_bayar ? (string) $p->waktu_bayar : null,
                'catatan' => $p->catatan,
            ])
            ->values()
            ->all();

        return [
            'total_transaksi' => count($pembayarans),
            'pembayaran' => $pembayarans,
        ];
    }

    /**
     * SIKEU: Daftar Beasiswa Mahasiswa & Potongan.
     */
    public function getSikeuBeasiswa(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $beasiswas = MahasiswaBeasiswa::with('beasiswa')
            ->where(function ($q) use ($mahasiswa) {
                $q->where('mahasiswa_id', $mahasiswa->id);
                if (! empty($mahasiswa->nim)) {
                    $q->orWhere('nim', $mahasiswa->nim);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (MahasiswaBeasiswa $mb) => [
                'id' => $mb->id,
                'status' => $mb->status,
                'berlaku_mulai' => $mb->berlaku_mulai,
                'berlaku_sampai' => $mb->berlaku_sampai,
                'program_beasiswa' => $mb->beasiswa ? [
                    'id' => $mb->beasiswa->id,
                    'kode' => $mb->beasiswa->kode ?? $mb->beasiswa->kode_beasiswa ?? null,
                    'nama' => $mb->beasiswa->nama ?? $mb->beasiswa->nama_beasiswa ?? null,
                    'tipe_potongan' => $mb->beasiswa->tipe_potongan ?? null,
                    'nilai_potongan' => isset($mb->beasiswa->nilai_potongan) ? (float) $mb->beasiswa->nilai_potongan : null,
                ] : null,
            ])
            ->values()
            ->all();

        return [
            'beasiswa' => $beasiswas,
        ];
    }

    /**
     * SIKEU: Riwayat Pengajuan Dispensasi Mahasiswa (beserta deteksi tunggakan dispensasi sebelumnya).
     */
    public function getSikeuDispensasi(int $userId): array
    {
        $mahasiswa = $this->resolveAuthenticatedMahasiswa($userId);

        $dispensasis = DispensasiTagihan::with('tagihan')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->orderByDesc('id')
            ->get();

        $memilikiTunggakanDispensasi = false;

        $items = $dispensasis->map(function (DispensasiTagihan $d) use (&$memilikiTunggakanDispensasi) {
            $tagihan = $d->tagihan;
            $sisaTagihan = $tagihan
                ? max(0, ($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan) - $tagihan->total_bayar)
                : 0;

            if ($d->status === 'approved' && $sisaTagihan > 0) {
                $memilikiTunggakanDispensasi = true;
            }

            return [
                'id' => $d->id,
                'tagihan_id' => $d->tagihan_id,
                'nomor_tagihan' => $tagihan?->nomor_tagihan,
                'tipe_dispensasi' => $d->tipe_dispensasi,
                'jatuh_tempo_baru' => $d->jatuh_tempo_baru ? $d->jatuh_tempo_baru->format('Y-m-d') : null,
                'jumlah_cicilan' => $d->jumlah_cicilan,
                'nominal_per_cicilan' => $d->nominal_per_cicilan !== null ? (float) $d->nominal_per_cicilan : null,
                'alasan' => $d->alasan,
                'allow_krs' => (bool) $d->allow_krs,
                'status' => $d->status,
                'sisa_tagihan_terkait' => (float) $sisaTagihan,
                'catatan_pimpinan' => $d->catatan_pimpinan,
            ];
        })->values()->all();

        return [
            'memiliki_tunggakan_dispensasi_sebelumnya' => $memilikiTunggakanDispensasi,
            'peringatan_tunggakan' => $memilikiTunggakanDispensasi
                ? 'Anda masih memiliki dispensasi sebelumnya yang tagihannya belum dilunasi.'
                : null,
            'dispensasi' => $items,
        ];
    }
}
