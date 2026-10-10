<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PengajuanCuti;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceRecapService
{
    /**
     * Nama bulan Indonesia singkat (deterministik, tidak tergantung locale).
     */
    private const BULAN_ID = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * Nama hari Indonesia (0=Minggu .. 6=Sabtu, mengikuti Carbon::dayOfWeek).
     */
    private const HARI_ID = [
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu',
    ];

    public static function formatTanggalId(Carbon $date): string
    {
        return sprintf('%d %s %d', $date->day, self::BULAN_ID[$date->month], $date->year);
    }

    public static function namaHariId(int $dayOfWeek): string
    {
        return self::HARI_ID[$dayOfWeek] ?? '';
    }

    /**
     * Menghasilkan rekapan presensi harian per pegawai untuk rentang tanggal tertentu.
     */
    public function generateRecap(Pegawai $employee, Carbon $startDate, Carbon $endDate): array
    {
        $employee->loadMissing(['shiftTemplate.days']);
        $period = CarbonPeriod::create($startDate, $endDate);

        $attendances = Attendance::where('pegawai_id', $employee->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->tanggal)->toDateString());

        $holidays = NationalHoliday::whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->holiday_date)->toDateString());

        // Petakan cuti yang sudah disetujui per tanggal (untuk keterangan ketidakhadiran).
        $approvedLeaves = $this->approvedLeavesByDate($employee->id, $startDate, $endDate);

        $recapRows = [];
        $totalHadir = 0;
        $totalTerlambat = 0;
        $totalAlpa = 0;
        $totalLibur = 0;
        $totalIzin = 0;
        $totalSakit = 0;
        $totalDinas = 0;
        $totalCuti = 0;

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $dayOfWeek = $date->dayOfWeek;
            $schedule = $employee->shiftTemplate?->getScheduleForDay($dayOfWeek);
            $attendance = $attendances->get($dateStr);
            $holiday = $holidays->get($dateStr);

            $scanMasuk = '-';
            $terlambat = '-';
            $scanPulang = '-';
            $keterangan = '-';
            $statusBadge = 'belum_absen';

            if ($attendance) {
                if ($attendance->clock_in) {
                    // Kolom rekap berupa wall-time WIB (disimpan UTC di DB).
                    $scanMasuk = $attendance->clock_in->copy()->setTimezone('Asia/Jakarta')->format('H:i:s');
                }
                if ($attendance->clock_out) {
                    $scanPulang = $attendance->clock_out->copy()->setTimezone('Asia/Jakarta')->format('H:i:s');
                }

                if ($attendance->late_minutes && $attendance->late_minutes > 0) {
                    $terlambat = $attendance->late_formatted;
                    $totalTerlambat++;
                    $statusBadge = 'terlambat';
                } elseif ($attendance->status === 'hadir') {
                    $totalHadir++;
                    $statusBadge = 'hadir';
                }

                if ($attendance->status === 'ditolak') {
                    $keterangan = 'Ditolak: ' . ($attendance->rejection_reason ?: 'Melanggar aturan');
                    $statusBadge = 'ditolak';
                } elseif ($attendance->status === 'menunggu_approval') {
                    $keterangan = 'Menunggu Persetujuan HR';
                    $statusBadge = 'menunggu_approval';
                } elseif (in_array($attendance->status, ['izin', 'sakit', 'dinas', 'alfa'], true)) {
                    // Keterangan ketidakhadiran yang ditetapkan HR (tanpa data scan).
                    $keterangan = $attendance->notes ?: $this->absenceLabel($attendance->status);
                    $statusBadge = $attendance->status;
                    match ($attendance->status) {
                        'izin' => $totalIzin++,
                        'sakit' => $totalSakit++,
                        'dinas' => $totalDinas++,
                        default => $totalAlpa++,
                    };
                } elseif ($attendance->notes) {
                    $keterangan = $attendance->notes;
                }
            } else {
                $leave = $approvedLeaves[$dateStr] ?? null;
                if ($holiday) {
                    $keterangan = $holiday->name;
                    $statusBadge = 'libur_nasional';
                    $totalLibur++;
                } elseif ($schedule && $schedule->is_day_off) {
                    $keterangan = 'Libur Reguler';
                    $statusBadge = 'libur_reguler';
                    $totalLibur++;
                } elseif ($leave) {
                    $keterangan = $leave['label'] . ' (disetujui)';
                    $statusBadge = $leave['badge'];
                    match ($leave['badge']) {
                        'sakit' => $totalSakit++,
                        'izin' => $totalIzin++,
                        default => $totalCuti++,
                    };
                } elseif ($date->isPast()) {
                    $keterangan = 'Tidak Hadir (Alpa)';
                    $statusBadge = 'alpa';
                    $totalAlpa++;
                }
            }

            $recapRows[] = [
                'date' => $dateStr,
                'tanggal' => self::formatTanggalId($date),
                'day_name' => self::namaHariId($date->dayOfWeek),
                'hari' => $schedule ? $schedule->day_name : self::namaHariId($date->dayOfWeek),
                'scan_masuk' => $scanMasuk,
                'terlambat' => $terlambat,
                'scan_pulang' => $scanPulang,
                'keterangan' => $keterangan,
                'status_badge' => $statusBadge,
            ];
        }

        return [
            'employee' => $employee,
            'period' => [
                'start' => $startDate->format('d F Y'),
                'end' => $endDate->format('d F Y'),
                'month_year' => $startDate->format('F Y'),
            ],
            'summary' => [
                'total_hadir' => $totalHadir,
                'total_terlambat' => $totalTerlambat,
                'total_alpa' => $totalAlpa,
                'total_libur' => $totalLibur,
                'total_izin' => $totalIzin,
                'total_sakit' => $totalSakit,
                'total_dinas' => $totalDinas,
                'total_cuti' => $totalCuti,
            ],
            'rows' => $recapRows,
        ];
    }

    /**
     * Sajikan rekap ke dalam kontrak frontend (dokumen
     * BACKEND_FIX_CLOCKOUT_DAN_RIWAYAT §3.2):
     * `{employee, filter, summary, rows}` dengan kolom tabel
     * `tanggal | scan_masuk | terlambat | scan_pulang | keterangan`.
     * Kunci lama (`period`, `hari`) dipertahankan sebagai alias.
     *
     * @return array{employee: array, filter: array, summary: array, rows: array, period: array}
     */
    public function presentRecap(Pegawai $employee, Carbon $startDate, Carbon $endDate): array
    {
        $recap = $this->generateRecap($employee, $startDate, $endDate);

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->nip,
                'name' => $employee->nama_lengkap,
                'department' => $employee->department,
                'position' => $employee->position,
                'shift_name' => $employee->shiftTemplate?->name,
            ],
            'filter' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => array_merge(
                ['total_days' => $startDate->diffInDays($endDate) + 1],
                $recap['summary'],
            ),
            'rows' => $recap['rows'],
            // Alias kompatibilitas mundur untuk klien lama.
            'period' => $recap['period'],
        ];
    }

    /**
     * Petakan pengajuan cuti berstatus approved per tanggal dalam rentang.
     *
     * @return array<string, array{badge: string, label: string}>
     */
    private function approvedLeavesByDate(int $pegawaiId, Carbon $startDate, Carbon $endDate): array
    {
        $leaves = PengajuanCuti::where('pegawai_id', $pegawaiId)
            ->where('status_approval', 'approved')
            ->whereDate('tanggal_mulai', '<=', $endDate->toDateString())
            ->whereDate('tanggal_selesai', '>=', $startDate->toDateString())
            ->get();

        $map = [];
        foreach ($leaves as $leave) {
            try {
                $from = Carbon::parse($leave->tanggal_mulai)->startOfDay();
                $to = Carbon::parse($leave->tanggal_selesai)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($to->lessThan($startDate) || $from->greaterThan($endDate)) {
                continue;
            }

            $badge = $leave->jenis_cuti === 'sakit' ? 'sakit' : 'cuti';
            $label = $this->leaveLabel((string) $leave->jenis_cuti);

            foreach (CarbonPeriod::create($from, $to) as $day) {
                $key = $day->toDateString();
                if ($key >= $startDate->toDateString() && $key <= $endDate->toDateString()) {
                    $map[$key] ??= ['badge' => $badge, 'label' => $label];
                }
            }
        }

        return $map;
    }

    private function leaveLabel(string $jenisCuti): string
    {
        return match ($jenisCuti) {
            'tahunan' => 'Cuti Tahunan',
            'sakit' => 'Sakit',
            'melahirkan' => 'Cuti Melahirkan',
            'alasan_penting' => 'Izin Alasan Penting',
            'besar' => 'Cuti Besar',
            default => ucfirst(str_replace('_', ' ', $jenisCuti)),
        };
    }

    private function absenceLabel(string $status): string
    {
        return match ($status) {
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'dinas' => 'Dinas Luar',
            'alfa' => 'Tidak Hadir (Alpa)',
            default => ucfirst($status),
        };
    }
}
