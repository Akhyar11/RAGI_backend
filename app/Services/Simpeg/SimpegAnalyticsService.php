<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\Simpeg\SertifikasiDosen;
use App\Models\Simpeg\SuratPeringatan;
use App\Models\Simpeg\UnitKerja;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SimpegAnalyticsService
{
    /**
     * Mengambil data analitik presensi & kedisiplinan untuk tahun tertentu.
     * Tab 1:
     *  - Grafik 1: Tren Keterlambatan & Kehadiran Bulanan (12 Bulan)
     *  - Grafik 2: Tren Penerbitan Surat Peringatan (SP) (12 Bulan)
     *  - Grafik 3: Top 5 Unit Kerja Paling Disiplin vs Rawan Terlambat
     */
    public function getPresensiAnalytics(?int $tahun = null): array
    {
        $tahun = $tahun ?: (int) Carbon::now()->format('Y');

        $monthNames = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
        ];

        // -------------------------------------------------------------
        // 1. DATA TREN KEHADIRAN 12 BULAN (Grafik 1)
        // -------------------------------------------------------------
        $attendanceMap = [];
        foreach ($monthNames as $mStr => $mLabel) {
            $attendanceMap[$mStr] = [
                'month_code' => $mStr,
                'month_name' => $mLabel,
                'tepat_waktu' => 0,
                'terlambat' => 0,
                'izin_sakit_cuti' => 0,
                'alfa' => 0,
                'total_kehadiran' => 0,
            ];
        }

        // A. Dari Log yang terikat bundle periode
        $bundleRecords = PresensiPegawai::join('simpeg_presensi_periode', 'simpeg_presensi_pegawai.presensi_periode_id', '=', 'simpeg_presensi_periode.id')
            ->where('simpeg_presensi_periode.bulan_tahun', 'like', "{$tahun}-%")
            ->selectRaw("
                substr(simpeg_presensi_periode.bulan_tahun, 6, 2) as bulan,
                sum(case when (simpeg_presensi_pegawai.status_kehadiran = 'hadir' or simpeg_presensi_pegawai.status = 'hadir' or simpeg_presensi_pegawai.status_kehadiran is null) and (simpeg_presensi_pegawai.late_minutes is null or simpeg_presensi_pegawai.late_minutes = 0) then 1 else 0 end) as tepat_waktu,
                sum(case when simpeg_presensi_pegawai.late_minutes > 0 or simpeg_presensi_pegawai.status_kehadiran = 'terlambat' or simpeg_presensi_pegawai.status = 'terlambat' then 1 else 0 end) as terlambat,
                sum(case when simpeg_presensi_pegawai.status_kehadiran in ('izin', 'sakit', 'cuti') or simpeg_presensi_pegawai.status in ('izin', 'sakit', 'cuti') then 1 else 0 end) as izin_sakit_cuti,
                sum(case when simpeg_presensi_pegawai.status_kehadiran = 'alfa' or simpeg_presensi_pegawai.status = 'alfa' then 1 else 0 end) as alfa,
                count(*) as total_record
            ")
            ->groupBy('bulan')
            ->get();

        foreach ($bundleRecords as $r) {
            $m = $r->bulan;
            if (isset($attendanceMap[$m])) {
                $attendanceMap[$m]['tepat_waktu'] += (int) $r->tepat_waktu;
                $attendanceMap[$m]['terlambat'] += (int) $r->terlambat;
                $attendanceMap[$m]['izin_sakit_cuti'] += (int) $r->izin_sakit_cuti;
                $attendanceMap[$m]['alfa'] += (int) $r->alfa;
                $attendanceMap[$m]['total_kehadiran'] += (int) $r->total_record;
            }
        }

        // B. Dari Log standalone realtime (belum bundle)
        $standaloneRecords = PresensiPegawai::whereNull('presensi_periode_id')
            ->whereYear('tanggal', $tahun)
            ->selectRaw("
                substr(tanggal, 6, 2) as bulan,
                sum(case when (status_kehadiran = 'hadir' or status = 'hadir' or status_kehadiran is null) and (late_minutes is null or late_minutes = 0) then 1 else 0 end) as tepat_waktu,
                sum(case when late_minutes > 0 or status_kehadiran = 'terlambat' or status = 'terlambat' then 1 else 0 end) as terlambat,
                sum(case when status_kehadiran in ('izin', 'sakit', 'cuti') or status in ('izin', 'sakit', 'cuti') then 1 else 0 end) as izin_sakit_cuti,
                sum(case when status_kehadiran = 'alfa' or status = 'alfa' then 1 else 0 end) as alfa,
                count(*) as total_record
            ")
            ->groupBy('bulan')
            ->get();

        foreach ($standaloneRecords as $r) {
            $m = $r->bulan;
            if (isset($attendanceMap[$m])) {
                $attendanceMap[$m]['tepat_waktu'] += (int) $r->tepat_waktu;
                $attendanceMap[$m]['terlambat'] += (int) $r->terlambat;
                $attendanceMap[$m]['izin_sakit_cuti'] += (int) $r->izin_sakit_cuti;
                $attendanceMap[$m]['alfa'] += (int) $r->alfa;
                $attendanceMap[$m]['total_kehadiran'] += (int) $r->total_record;
            }
        }

        $monthlyAttendance = array_values($attendanceMap);

        // -------------------------------------------------------------
        // 2. DATA TREN PENERBITAN SP 12 BULAN (Grafik 2)
        // -------------------------------------------------------------
        $spMap = [];
        foreach ($monthNames as $mStr => $mLabel) {
            $spMap[$mStr] = [
                'month_code' => $mStr,
                'month_name' => $mLabel,
                'sp_1' => 0,
                'sp_2' => 0,
                'sp_3' => 0,
                'total_sp' => 0,
            ];
        }

        $spList = SuratPeringatan::where('periode_bulan_tahun', 'like', "{$tahun}-%")
            ->selectRaw("
                substr(periode_bulan_tahun, 6, 2) as bulan,
                sum(case when jenis_sp = 'SP 1' then 1 else 0 end) as sp_1,
                sum(case when jenis_sp = 'SP 2' then 1 else 0 end) as sp_2,
                sum(case when jenis_sp = 'SP 3' then 1 else 0 end) as sp_3,
                count(*) as total_sp
            ")
            ->groupBy('bulan')
            ->get();

        foreach ($spList as $sp) {
            $m = $sp->bulan;
            if (isset($spMap[$m])) {
                $spMap[$m]['sp_1'] = (int) $sp->sp_1;
                $spMap[$m]['sp_2'] = (int) $sp->sp_2;
                $spMap[$m]['sp_3'] = (int) $sp->sp_3;
                $spMap[$m]['total_sp'] = (int) $sp->total_sp;
            }
        }

        $monthlySp = array_values($spMap);

        // -------------------------------------------------------------
        // 3. TOP 5 UNIT KERJA DISIPLIN VS RAWAN (Grafik 3)
        // -------------------------------------------------------------
        $unitStats = PresensiPegawai::join('simpeg_pegawai', 'simpeg_presensi_pegawai.pegawai_id', '=', 'simpeg_pegawai.id')
            ->join('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.id')
            ->leftJoin('simpeg_presensi_periode', 'simpeg_presensi_pegawai.presensi_periode_id', '=', 'simpeg_presensi_periode.id')
            ->where(function ($q) use ($tahun) {
                $q->where('simpeg_presensi_periode.bulan_tahun', 'like', "{$tahun}-%")
                  ->orWhere(function ($sq) use ($tahun) {
                      $sq->whereNull('simpeg_presensi_pegawai.presensi_periode_id')
                         ->whereYear('simpeg_presensi_pegawai.tanggal', $tahun);
                  });
            })
            ->selectRaw("
                simpeg_unit_kerja.id as unit_kerja_id,
                simpeg_unit_kerja.nama as unit_kerja_nama,
                count(*) as total_presensi,
                sum(case when (simpeg_presensi_pegawai.late_minutes is null or simpeg_presensi_pegawai.late_minutes = 0) and (simpeg_presensi_pegawai.status_kehadiran = 'hadir' or simpeg_presensi_pegawai.status = 'hadir' or simpeg_presensi_pegawai.status_kehadiran is null) then 1 else 0 end) as total_tepat_waktu,
                sum(case when simpeg_presensi_pegawai.late_minutes > 0 or simpeg_presensi_pegawai.status_kehadiran = 'terlambat' or simpeg_presensi_pegawai.status = 'terlambat' then 1 else 0 end) as total_terlambat
            ")
            ->groupBy('simpeg_unit_kerja.id', 'simpeg_unit_kerja.nama')
            ->having('total_presensi', '>', 0)
            ->get();

        $unitList = $unitStats->map(function ($item) {
            $total = (int) $item->total_presensi;
            $tepat = (int) $item->total_tepat_waktu;
            $terlambat = (int) $item->total_terlambat;
            $persenDisiplin = $total > 0 ? round(($tepat / $total) * 100, 1) : 0.0;

            return [
                'unit_kerja_id' => $item->unit_kerja_id,
                'unit_kerja_nama' => $item->unit_kerja_nama,
                'total_presensi' => $total,
                'total_tepat_waktu' => $tepat,
                'total_terlambat' => $terlambat,
                'persentase_disiplin' => $persenDisiplin,
            ];
        });

        // Top 5 Paling Disiplin (persentase tepat waktu tertinggi)
        $topDisiplin = $unitList->sortByDesc('persentase_disiplin')
            ->sortByDesc('total_tepat_waktu')
            ->values()
            ->take(5)
            ->all();

        // Top 5 Paling Rawan (frekuensi keterlambatan terbanyak)
        $topRawan = $unitList->sortByDesc('total_terlambat')
            ->values()
            ->take(5)
            ->all();

        // Ringkasan KPI Tab 1
        $totalKehadiranTahun = array_sum(array_column($monthlyAttendance, 'total_kehadiran'));
        $totalTepatWaktuTahun = array_sum(array_column($monthlyAttendance, 'tepat_waktu'));
        $totalTerlambatTahun = array_sum(array_column($monthlyAttendance, 'terlambat'));
        $totalSpTahun = array_sum(array_column($monthlySp, 'total_sp'));
        $tingkatDisiplinGlobal = $totalKehadiranTahun > 0
            ? round(($totalTepatWaktuTahun / $totalKehadiranTahun) * 100, 1)
            : 100.0;

        return [
            'tahun' => $tahun,
            'summary' => [
                'total_kehadiran' => $totalKehadiranTahun,
                'total_tepat_waktu' => $totalTepatWaktuTahun,
                'total_terlambat' => $totalTerlambatTahun,
                'total_sp' => $totalSpTahun,
                'tingkat_disiplin_persen' => $tingkatDisiplinGlobal,
            ],
            'monthly_attendance' => $monthlyAttendance,
            'monthly_sp' => $monthlySp,
            'top_unit_kerja' => [
                'disiplin' => $topDisiplin,
                'rawan' => $topRawan,
            ],
        ];
    }

    /**
     * Mengambil data analitik demografi & kualifikasi SDM real-time.
     * Tab 2:
     *  - Grafik 4: Piramida Jabatan Fungsional Akademik (JAD) Dosen
     *  - Grafik 5: Rasio Dosen Bersertifikasi (Serdos)
     *  - Grafik 6: Penyebaran Kelompok Usia & Proyeksi Pensiun
     */
    public function getDemografiAnalytics(): array
    {
        // -------------------------------------------------------------
        // 1. DATA DOSEN & JAD (Grafik 4 & 5)
        // -------------------------------------------------------------
        $dosenQuery = Pegawai::where('is_active', true)
            ->where(function ($q) {
                $q->where('jenis_pegawai', 'like', '%dosen%')
                  ->orWhereHas('roles', function ($r) {
                      $r->where('slug', 'dosen')->orWhere('name', 'like', '%dosen%');
                  });
            });

        $totalDosen = $dosenQuery->count();
        $dosenList = $dosenQuery->with('jabatanFungsional')->get();
        $dosenIds = $dosenList->pluck('id');

        // Klasifikasi Piramida JAD Dosen (Standar Dikti / BAN-PT)
        $jadCounts = [
            'guru_besar' => 0,
            'lektor_kepala' => 0,
            'lektor' => 0,
            'asisten_ahli' => 0,
            'tenaga_pengajar' => 0,
        ];

        foreach ($dosenList as $dosen) {
            $namaJad = strtolower($dosen->jabatanFungsional?->nama ?? '');
            if (empty($namaJad) || str_contains($namaJad, 'tenaga pengajar')) {
                $jadCounts['tenaga_pengajar']++;
            } elseif (str_contains($namaJad, 'guru besar') || str_contains($namaJad, 'profesor')) {
                $jadCounts['guru_besar']++;
            } elseif (str_contains($namaJad, 'lektor kepala')) {
                $jadCounts['lektor_kepala']++;
            } elseif (str_contains($namaJad, 'lektor')) {
                $jadCounts['lektor']++;
            } elseif (str_contains($namaJad, 'asisten ahli')) {
                $jadCounts['asisten_ahli']++;
            } else {
                $jadCounts['tenaga_pengajar']++;
            }
        }

        $jadPyramid = [
            [
                'level' => 'guru_besar',
                'label' => 'Guru Besar / Profesor',
                'order' => 5,
                'count' => $jadCounts['guru_besar'],
                'percentage' => $totalDosen > 0 ? round(($jadCounts['guru_besar'] / $totalDosen) * 100, 1) : 0,
                'color' => '#8b5cf6', // Ungu
            ],
            [
                'level' => 'lektor_kepala',
                'label' => 'Lektor Kepala',
                'order' => 4,
                'count' => $jadCounts['lektor_kepala'],
                'percentage' => $totalDosen > 0 ? round(($jadCounts['lektor_kepala'] / $totalDosen) * 100, 1) : 0,
                'color' => '#3b82f6', // Biru
            ],
            [
                'level' => 'lektor',
                'label' => 'Lektor',
                'order' => 3,
                'count' => $jadCounts['lektor'],
                'percentage' => $totalDosen > 0 ? round(($jadCounts['lektor'] / $totalDosen) * 100, 1) : 0,
                'color' => '#06b6d4', // Cyan
            ],
            [
                'level' => 'asisten_ahli',
                'label' => 'Asisten Ahli',
                'order' => 2,
                'count' => $jadCounts['asisten_ahli'],
                'percentage' => $totalDosen > 0 ? round(($jadCounts['asisten_ahli'] / $totalDosen) * 100, 1) : 0,
                'color' => '#10b981', // Hijau
            ],
            [
                'level' => 'tenaga_pengajar',
                'label' => 'Tenaga Pengajar (Belum JAD)',
                'order' => 1,
                'count' => $jadCounts['tenaga_pengajar'],
                'percentage' => $totalDosen > 0 ? round(($jadCounts['tenaga_pengajar'] / $totalDosen) * 100, 1) : 0,
                'color' => '#94a3b8', // Abu-abu
            ],
        ];

        // -------------------------------------------------------------
        // 2. RASIO SERTIFIKASI DOSEN (Grafik 5)
        // -------------------------------------------------------------
        $certifiedDosenCount = SertifikasiDosen::whereIn('pegawai_id', $dosenIds)
            ->distinct('pegawai_id')
            ->count('pegawai_id');

        $belumSerdos = max(0, $totalDosen - $certifiedDosenCount);
        $persenSerdos = $totalDosen > 0 ? round(($certifiedDosenCount / $totalDosen) * 100, 1) : 0.0;

        $serdosRatio = [
            'total_dosen' => $totalDosen,
            'tersertifikasi' => $certifiedDosenCount,
            'belum_tersertifikasi' => $belumSerdos,
            'persentase' => $persenSerdos,
        ];

        // -------------------------------------------------------------
        // 3. PENYEBARAN KELOMPOK USIA & PROYEKSI PENSIUN (Grafik 6)
        // -------------------------------------------------------------
        $allPegawai = Pegawai::where('is_active', true)
            ->whereNotNull('tanggal_lahir')
            ->get();

        $totalPegawaiWithBirth = $allPegawai->count();
        $now = Carbon::now();

        $ageGroups = [
            'under_30' => ['label' => '< 30 Tahun', 'sublabel' => 'Generasi Baru', 'count' => 0, 'color' => '#0ea5e9'],
            '30_39'    => ['label' => '30 - 39 Tahun', 'sublabel' => 'Produktif Awal', 'count' => 0, 'color' => '#10b981'],
            '40_49'    => ['label' => '40 - 49 Tahun', 'sublabel' => 'Produktif Madya', 'count' => 0, 'color' => '#f59e0b'],
            '50_59'    => ['label' => '50 - 59 Tahun', 'sublabel' => 'Senior', 'count' => 0, 'color' => '#6366f1'],
            '60_plus'  => ['label' => '≥ 60 Tahun', 'sublabel' => 'Masa Pensiun (MPP)', 'count' => 0, 'color' => '#ef4444'],
        ];

        foreach ($allPegawai as $pegawai) {
            $birthDate = Carbon::parse($pegawai->tanggal_lahir);
            $age = $birthDate->diffInYears($now);

            if ($age < 30) {
                $ageGroups['under_30']['count']++;
            } elseif ($age <= 39) {
                $ageGroups['30_39']['count']++;
            } elseif ($age <= 49) {
                $ageGroups['40_49']['count']++;
            } elseif ($age <= 59) {
                $ageGroups['50_59']['count']++;
            } else {
                $ageGroups['60_plus']['count']++;
            }
        }

        $ageDistribution = [];
        foreach ($ageGroups as $key => $group) {
            $cnt = $group['count'];
            $pct = $totalPegawaiWithBirth > 0 ? round(($cnt / $totalPegawaiWithBirth) * 100, 1) : 0;
            $ageDistribution[] = [
                'group' => $key,
                'label' => $group['label'],
                'sublabel' => $group['sublabel'],
                'count' => $cnt,
                'percentage' => $pct,
                'color' => $group['color'],
                'is_mpp' => ($key === '60_plus'),
            ];
        }

        return [
            'jad_pyramid' => $jadPyramid,
            'serdos_ratio' => $serdosRatio,
            'age_distribution' => $ageDistribution,
            'summary' => [
                'total_dosen' => $totalDosen,
                'total_pegawai_terdata' => $totalPegawaiWithBirth,
                'persentase_dosen_ber_jad' => $totalDosen > 0 ? round((($totalDosen - $jadCounts['tenaga_pengajar']) / $totalDosen) * 100, 1) : 0,
                'total_mpp_pensiun' => $ageGroups['60_plus']['count'],
            ],
        ];
    }
}
