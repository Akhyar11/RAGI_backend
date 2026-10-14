<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\SuratPeringatan;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SuratPeringatanService
{
    /**
     * Periksa dan terbitkan SP untuk satu pegawai setelah penggajian berstatus paid.
     */
    public function checkAndGenerateSpForGaji(GajiPegawai $gaji): ?SuratPeringatan
    {
        if ($gaji->status_transfer !== 'paid') {
            return null;
        }

        $pegawai = $gaji->pegawai;
        if (!$pegawai) {
            return null;
        }

        $periode = $gaji->periode_bulan_tahun; // Format: 'YYYY-MM', misal '2026-10'
        $maxLateAllowed = (int) SystemSetting::get('max_late_per_month', 5);

        // Cari bundle presensi terkait
        $bundle = PresensiPeriode::where('bulan_tahun', $periode)
            ->orWhere('nama_periode', 'like', "%{$periode}%")
            ->first();

        // Hitung total keterlambatan pegawai pada periode/bulan tersebut
        $totalTerlambat = $this->calculateLateCountForPegawai($pegawai->id, $periode, $bundle);

        if ($totalTerlambat > $maxLateAllowed) {
            return $this->createOrUpdateSp($pegawai, $gaji, $bundle, $periode, $totalTerlambat, $maxLateAllowed);
        }

        return null;
    }

    /**
     * Periksa dan terbitkan SP untuk seluruh pegawai dalam satu periode payroll.
     */
    public function checkAndGenerateSpForPeriode(string $periode): int
    {
        $gajiList = GajiPegawai::where('periode_bulan_tahun', $periode)
            ->where('status_transfer', 'paid')
            ->get();

        $count = 0;
        foreach ($gajiList as $gaji) {
            $sp = $this->checkAndGenerateSpForGaji($gaji);
            if ($sp) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Hitung total keterlambatan (status='terlambat') pegawai dalam rentang periode.
     */
    public function calculateLateCountForPegawai(int $pegawaiId, string $periode, ?PresensiPeriode $bundle = null): int
    {
        $query = PresensiPegawai::where('pegawai_id', $pegawaiId)
            ->where(function ($q) {
                $q->where('status_kehadiran', 'terlambat')
                  ->orWhere('status', 'terlambat');
            });

        if ($bundle && $bundle->tanggal_awal && $bundle->tanggal_akhir) {
            $start = Carbon::parse($bundle->tanggal_awal)->format('Y-m-d');
            $end = Carbon::parse($bundle->tanggal_akhir)->format('Y-m-d');
            $query->whereBetween('tanggal', [$start, $end]);
        } else {
            $startOfMonth = Carbon::parse($periode . '-01')->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::parse($periode . '-01')->endOfMonth()->format('Y-m-d');
            $query->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);
        }

        return $query->count();
    }

    /**
     * Terbitkan atau perbarui record SP (idempoten).
     */
    protected function createOrUpdateSp(
        Pegawai $pegawai,
        GajiPegawai $gaji,
        ?PresensiPeriode $bundle,
        string $periode,
        int $totalTerlambat,
        int $maxLateAllowed
    ): SuratPeringatan {
        $existingSp = SuratPeringatan::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan_tahun', $periode)
            ->first();

        if ($existingSp) {
            $oldValues = $existingSp->getOriginal();
            $existingSp->update([
                'gaji_pegawai_id' => $gaji->id,
                'presensi_periode_id' => $bundle?->id,
                'total_terlambat' => $totalTerlambat,
                'batas_maksimal' => $maxLateAllowed,
            ]);
            $newValues = $existingSp->getChanges();

            try {
                AuditLogService::record(
                    module: 'SIMPEG',
                    action: 'update',
                    tableName: 'simpeg_surat_peringatan',
                    recordId: $existingSp->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log pembaruan SP: ' . $e->getMessage());
            }

            return $existingSp;
        }

        // Generate nomor SP resmi
        $year = Carbon::now()->format('Y');
        $month = Carbon::now()->format('m');
        $countThisYear = SuratPeringatan::whereYear('created_at', $year)->count() + 1;
        $nomorUrut = str_pad((string) $countThisYear, 4, '0', STR_PAD_LEFT);
        $nomorSp = "SP/SIMPEG/{$year}/{$month}/{$nomorUrut}";

        $namaBulan = Carbon::parse($periode . '-01')->locale('id')->isoFormat('MMMM Y');

        $sp = SuratPeringatan::create([
            'pegawai_id' => $pegawai->id,
            'gaji_pegawai_id' => $gaji->id,
            'presensi_periode_id' => $bundle?->id,
            'nomor_sp' => $nomorSp,
            'jenis_sp' => 'SP 1',
            'judul' => "Surat Peringatan Keterlambatan Presensi Periode {$namaBulan}",
            'periode_bulan_tahun' => $periode,
            'total_terlambat' => $totalTerlambat,
            'batas_maksimal' => $maxLateAllowed,
            'keterangan' => "Surat peringatan diterbitkan secara otomatis oleh sistem kepegawaian karena akumulasi keterlambatan presensi Anda pada periode {$namaBulan} tercatat sebanyak {$totalTerlambat} kali, melebihi batas toleransi maksimal yang ditetapkan kampus ({$maxLateAllowed} kali). Harap meningkatkan kedisiplinan dan kepatuhan jam kerja.",
            'tanggal_terbit' => Carbon::now()->toDateString(),
            'status' => 'aktif',
        ]);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_surat_peringatan',
                recordId: $sp->id,
                oldValues: null,
                newValues: $sp->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log penerbitan SP: ' . $e->getMessage());
        }

        return $sp;
    }

    /**
     * Mengambil matriks monitoring Surat Peringatan (SP) dan keterlambatan tahunan (12 bulan).
     */
    public function getAnnualMonitoring(int $tahun, array $filters = []): array
    {
        $maxLateAllowed = (int) SystemSetting::get('max_late_per_month', 5);

        // 1. Ambil list pegawai aktif sesuai filter
        $query = Pegawai::with('unitKerja')->where('is_active', true);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('nama_lengkap', 'like', "%{$s}%")
                  ->orWhere('nip', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', (int) $filters['unit_kerja_id']);
        }

        $pegawaiList = $query->orderBy('nama_lengkap', 'asc')->get();

        // 2. Ambil seluruh data SP pada tahun tersebut
        $spList = SuratPeringatan::where('periode_bulan_tahun', 'like', "{$tahun}-%")->get();
        $spMap = [];
        foreach ($spList as $sp) {
            $m = substr($sp->periode_bulan_tahun, 5, 2);
            $spMap[$sp->pegawai_id][$m] = $sp;
        }

        // 3. Ambil data bundle pada tahun tersebut
        $bundleList = PresensiPeriode::where('bulan_tahun', 'like', "{$tahun}-%")->get();
        $bundleMap = [];
        foreach ($bundleList as $b) {
            if ($b->bulan_tahun) {
                $m = substr($b->bulan_tahun, 5, 2);
                $bundleMap[$m] = true;
            }
        }

        // 4. Hitung keterlambatan presensi per pegawai per bulan pada tahun tersebut
        $lateList = PresensiPegawai::where('status_kehadiran', 'terlambat')
            ->whereYear('tanggal', $tahun)
            ->selectRaw("pegawai_id, substr(tanggal, 6, 2) as bulan, count(*) as total_late")
            ->groupBy('pegawai_id', 'bulan')
            ->get();
        $lateMap = [];
        foreach ($lateList as $item) {
            $lateMap[$item->pegawai_id][$item->bulan] = (int) $item->total_late;
        }

        // 5. Cek record presensi untuk deteksi ketersediaan data bulan berjalan
        $activeAttendanceList = PresensiPegawai::whereYear('tanggal', $tahun)
            ->selectRaw("pegawai_id, substr(tanggal, 6, 2) as bulan")
            ->groupBy('pegawai_id', 'bulan')
            ->get();
        $attendanceDataMap = [];
        foreach ($activeAttendanceList as $item) {
            $attendanceDataMap[$item->pegawai_id][$item->bulan] = true;
        }

        $monthNames = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
        ];

        $currentYearMonth = Carbon::now()->format('Y-m');

        $rows = [];
        $totalSpCountInYear = 0;
        $pegawaiWithSpCount = 0;

        foreach ($pegawaiList as $pegawai) {
            $monthsData = [];
            $totalSpPegawai = 0;
            $totalLatePegawai = 0;

            foreach ($monthNames as $mStr => $mShort) {
                $monthPeriod = sprintf('%04d-%s', $tahun, $mStr);
                $sp = $spMap[$pegawai->id][$mStr] ?? null;
                $lateCount = $lateMap[$pegawai->id][$mStr] ?? 0;
                $hasAttendance = isset($bundleMap[$mStr]) || isset($attendanceDataMap[$pegawai->id][$mStr]);
                $isFuture = $monthPeriod > $currentYearMonth;

                $hasSp = false;
                $color = 'neutral';

                if ($sp) {
                    $hasSp = true;
                    $totalSpPegawai++;
                    $lateCount = max($lateCount, $sp->total_terlambat);
                    $color = 'danger';
                } elseif ($lateCount > $maxLateAllowed) {
                    $color = 'danger';
                } elseif ($lateCount > 0) {
                    $color = 'warning';
                } elseif ($hasAttendance || (!$isFuture && $tahun < (int) Carbon::now()->format('Y'))) {
                    $color = 'success';
                } else {
                    $color = 'neutral';
                }

                $totalLatePegawai += $lateCount;

                $monthsData[$mStr] = [
                    'month' => (int) $mStr,
                    'month_code' => $mStr,
                    'month_name' => $mShort,
                    'periode' => $monthPeriod,
                    'has_data' => $hasAttendance || $sp !== null,
                    'is_future' => $isFuture,
                    'total_terlambat' => $lateCount,
                    'batas_maksimal' => $maxLateAllowed,
                    'has_sp' => $hasSp,
                    'sp_id' => $sp?->id,
                    'nomor_sp' => $sp?->nomor_sp,
                    'jenis_sp' => $sp?->jenis_sp,
                    'judul_sp' => $sp?->judul,
                    'status_sp' => $sp?->status,
                    'status_color' => $color,
                ];
            }

            if ($totalSpPegawai > 0) {
                $pegawaiWithSpCount++;
                $totalSpCountInYear += $totalSpPegawai;
            }

            $riskLevel = 'disiplin';
            $riskLabel = 'Disiplin (0 SP)';
            if ($totalSpPegawai >= 2) {
                $riskLevel = 'rawan';
                $riskLabel = 'Sering SP (Rawan)';
            } elseif ($totalSpPegawai === 1) {
                $riskLevel = 'perhatian';
                $riskLabel = 'Pernah SP (1x)';
            } elseif ($totalLatePegawai > 0) {
                $riskLevel = 'waspada';
                $riskLabel = 'Ada Keterlambatan';
            }

            if (!empty($filters['risk_level']) && $filters['risk_level'] !== 'all') {
                if ($filters['risk_level'] === 'rawan' && $totalSpPegawai < 2) {
                    continue;
                }
                if ($filters['risk_level'] === 'perhatian' && $totalSpPegawai !== 1) {
                    continue;
                }
                if ($filters['risk_level'] === 'has_sp' && $totalSpPegawai < 1) {
                    continue;
                }
                if ($filters['risk_level'] === 'disiplin' && $totalSpPegawai > 0) {
                    continue;
                }
            }

            $rows[] = [
                'pegawai_id' => $pegawai->id,
                'nip' => $pegawai->nip ?? '-',
                'nama_lengkap' => $pegawai->nama_lengkap,
                'unit_kerja' => $pegawai->unitKerja?->nama ?? 'Tidak Ada Unit',
                'months' => $monthsData,
                'summary' => [
                    'total_sp' => $totalSpPegawai,
                    'total_terlambat' => $totalLatePegawai,
                    'risk_level' => $riskLevel,
                    'risk_label' => $riskLabel,
                ],
            ];
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'total_sp';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        usort($rows, function ($a, $b) use ($sortBy, $sortOrder) {
            $valA = 0;
            $valB = 0;
            if ($sortBy === 'total_sp') {
                $valA = $a['summary']['total_sp'];
                $valB = $b['summary']['total_sp'];
            } elseif ($sortBy === 'total_terlambat') {
                $valA = $a['summary']['total_terlambat'];
                $valB = $b['summary']['total_terlambat'];
            } elseif ($sortBy === 'nama_lengkap') {
                return $sortOrder === 'asc'
                    ? strcmp($a['nama_lengkap'], $b['nama_lengkap'])
                    : strcmp($b['nama_lengkap'], $a['nama_lengkap']);
            } elseif ($sortBy === 'nip') {
                return $sortOrder === 'asc'
                    ? strcmp($a['nip'], $b['nip'])
                    : strcmp($b['nip'], $a['nip']);
            }

            if ($valA === $valB) {
                return strcmp($a['nama_lengkap'], $b['nama_lengkap']);
            }

            return $sortOrder === 'asc' ? ($valA <=> $valB) : ($valB <=> $valA);
        });

        $totalItems = count($rows);
        $perPage = min(100, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;
        $paginatedRows = array_slice($rows, $offset, $perPage);
        $lastPage = (int) ceil($totalItems / $perPage);

        return [
            'data' => $paginatedRows,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalItems,
                'last_page' => max(1, $lastPage),
                'tahun' => $tahun,
                'max_late_per_month' => $maxLateAllowed,
                'stats' => [
                    'total_pegawai' => count($pegawaiList),
                    'pegawai_pernah_sp' => $pegawaiWithSpCount,
                    'total_sp_terbit' => $totalSpCountInYear,
                ],
            ],
        ];
    }
}
