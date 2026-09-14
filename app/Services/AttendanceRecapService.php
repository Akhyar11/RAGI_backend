<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceRecapService
{
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

        $recapRows = [];
        $totalHadir = 0;
        $totalTerlambat = 0;
        $totalAlpa = 0;
        $totalLibur = 0;

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
                    $scanMasuk = $attendance->clock_in->format('H:i:s');
                }
                if ($attendance->clock_out) {
                    $scanPulang = $attendance->clock_out->format('H:i:s');
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
                } elseif ($attendance->notes) {
                    $keterangan = $attendance->notes;
                }
            } else {
                if ($holiday) {
                    $keterangan = $holiday->name;
                    $statusBadge = 'libur_nasional';
                    $totalLibur++;
                } elseif ($schedule && $schedule->is_day_off) {
                    $keterangan = 'Libur Reguler';
                    $statusBadge = 'libur_reguler';
                    $totalLibur++;
                } elseif ($date->isPast()) {
                    $keterangan = 'Tidak Hadir (Alpa)';
                    $statusBadge = 'alpa';
                    $totalAlpa++;
                }
            }

            $recapRows[] = [
                'tanggal' => $date->format('d/m/Y'),
                'hari' => $schedule ? $schedule->day_name : $date->translatedFormat('l'),
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
            ],
            'rows' => $recapRows,
        ];
    }
}
