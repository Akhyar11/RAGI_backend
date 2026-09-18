<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\Pegawai;
use Carbon\Carbon;

class AttendanceCutoffService
{
    /**
     * Jalankan proses cut-off harian untuk menandai pegawai yang tidak hadir
     * dan tidak memiliki keterangan perizinan resmi sebagai Alfa.
     *
     * @param string|null $date
     * @param int|null $unitKerjaId
     * @return array
     */
    public function runDailyCutoff(?string $date = null, ?int $unitKerjaId = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();
        $dateStr = $targetDate->toDateString();
        $dayOfWeek = $targetDate->dayOfWeek;

        $isNationalHoliday = NationalHoliday::isHoliday($targetDate);

        $query = Pegawai::with(['shiftTemplate.days', 'unitKerja'])
            ->where(function ($q) {
                $q->where('status', 'aktif')
                  ->orWhere('is_active', true);
            });

        if ($unitKerjaId) {
            $query->where('unit_kerja_id', $unitKerjaId);
        }

        $employees = $query->get();

        $evaluated = 0;
        $markedAlfa = [];

        foreach ($employees as $employee) {
            $evaluated++;

            $schedule = $employee->shiftTemplate?->getScheduleForDay($dayOfWeek);

            // Jika tidak ada jadwal atau merupakan hari libur reguler shift karyawan
            if (!$schedule || $schedule->is_day_off) {
                continue;
            }

            // Jika hari ini libur nasional dan shift karyawan mematuhi libur nasional
            $appliesHoliday = $schedule->appliesNationalHolidays();
            if ($isNationalHoliday && $appliesHoliday) {
                continue;
            }

            // Shift lintas hari (misal 22:00-06:00) yang belum selesai jangan
            // ditandai Alfa — beri kesempatan clock-in susulan / clock-out pagi.
            if ($schedule->isOvernight()) {
                $scheduledEnd = $schedule->getScheduledEndForDate($dateStr);
                if (Carbon::now()->lessThan($scheduledEnd)) {
                    continue;
                }
            }

            // Cek rekaman presensi pada hari tersebut
            $existing = Attendance::where('pegawai_id', $employee->id)
                ->whereDate('tanggal', $dateStr)
                ->first();

            // Jika sudah ada clock in atau sudah ada status sah (hadir, terlambat, izin, sakit, dinas, dsb.)
            if ($existing) {
                if ($existing->clock_in || in_array($existing->status, ['hadir', 'terlambat', 'izin', 'sakit', 'dinas', 'menunggu_approval'])) {
                    continue;
                }
                if ($existing->status === 'alfa') {
                    // Sudah ditandai alfa sebelumnya
                    continue;
                }
            }

            // Tandai sebagai alfa
            $record = $existing ?? new Attendance([
                'pegawai_id' => $employee->id,
                'tanggal' => $dateStr,
            ]);

            $record->status = 'alfa';
            $record->status_kehadiran = 'alfa';
            $record->source = 'system_cutoff';
            $record->notes = 'Otomatis Cut-off Harian: Tidak Hadir / Alpha tanpa keterangan (Jadwal masuk: ' . ($schedule->start_time ?? '08:00') . ')';
            $record->is_approved_by_admin = true;
            $record->save();

            $markedAlfa[] = [
                'pegawai_id' => $employee->id,
                'nip' => $employee->nip,
                'nama' => $employee->nama_lengkap,
                'unit_kerja' => $employee->unitKerja?->nama,
                'shift' => $employee->shiftTemplate?->name,
                'scheduled_start' => $schedule->start_time,
            ];
        }

        return [
            'date' => $dateStr,
            'is_national_holiday' => (bool)$isNationalHoliday,
            'total_evaluated' => $evaluated,
            'total_marked_alfa' => count($markedAlfa),
            'marked_alfa_employees' => $markedAlfa,
        ];
    }
}
