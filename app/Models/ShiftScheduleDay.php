<?php

namespace App\Models;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftScheduleDay extends Model
{
    use HasFactory;

    protected $table = 'simpeg_shift_schedule_days';

    protected $fillable = [
        'shift_template_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'is_day_off',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
        'max_early_clock_in_minutes',
        'max_late_clock_in_minutes',
        'max_early_clock_out_minutes',
        'max_late_clock_out_minutes',
        'applies_national_holidays',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_day_off' => 'boolean',
        'late_tolerance_minutes' => 'integer',
        'early_leave_tolerance_minutes' => 'integer',
        'max_early_clock_in_minutes' => 'integer',
        'max_late_clock_in_minutes' => 'integer',
        'max_early_clock_out_minutes' => 'integer',
        'max_late_clock_out_minutes' => 'integer',
        'applies_national_holidays' => 'boolean',
    ];

    public function shiftTemplate()
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function appliesNationalHolidays(): bool
    {
        if ($this->applies_national_holidays !== null) {
            return (bool) $this->applies_national_holidays;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->applies_national_holidays !== null) {
            return (bool) $this->shiftTemplate->applies_national_holidays;
        }

        return true;
    }

    public function getLateToleranceMinutes(): int
    {
        if ($this->late_tolerance_minutes !== null) {
            return (int) $this->late_tolerance_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->late_tolerance_minutes !== null) {
            return (int) $this->shiftTemplate->late_tolerance_minutes;
        }

        return (int) SystemSetting::get('late_tolerance_minutes', 15);
    }

    public function getEarlyLeaveToleranceMinutes(): int
    {
        if ($this->early_leave_tolerance_minutes !== null) {
            return (int) $this->early_leave_tolerance_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->early_leave_tolerance_minutes !== null) {
            return (int) $this->shiftTemplate->early_leave_tolerance_minutes;
        }

        return (int) SystemSetting::get('early_leave_tolerance_minutes', 15);
    }

    /**
     * Batas buka absen lebih awal (menit sebelum jam mulai shift).
     * Hierarki: hari -> template -> SystemSetting (default 60).
     */
    public function getMaxEarlyClockInMinutes(): int
    {
        if ($this->max_early_clock_in_minutes !== null) {
            return (int) $this->max_early_clock_in_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->max_early_clock_in_minutes !== null) {
            return (int) $this->shiftTemplate->max_early_clock_in_minutes;
        }

        return (int) SystemSetting::get('max_early_clock_in_minutes', 60);
    }

    /**
     * Batas maksimal keterlambatan clock-in (menit setelah jam mulai shift).
     * Hierarki: hari -> template -> SystemSetting (default 240).
     * Nilai 0 = tanpa batas (selalu terima keterlambatan sebagai 'terlambat').
     */
    public function getMaxLateClockInMinutes(): int
    {
        if ($this->max_late_clock_in_minutes !== null) {
            return (int) $this->max_late_clock_in_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->max_late_clock_in_minutes !== null) {
            return (int) $this->shiftTemplate->max_late_clock_in_minutes;
        }

        return (int) SystemSetting::get('max_late_clock_in_minutes', 240);
    }

    /**
     * Batas buka absen pulang lebih awal (menit sebelum jam pulang shift).
     * Hierarki: hari -> template -> null (bila null, sistem otomatis membuka setelah batas masuk berakhir).
     */
    public function getMaxEarlyClockOutMinutes(): ?int
    {
        if ($this->max_early_clock_out_minutes !== null) {
            return (int) $this->max_early_clock_out_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->max_early_clock_out_minutes !== null) {
            return (int) $this->shiftTemplate->max_early_clock_out_minutes;
        }

        $global = SystemSetting::get('max_early_clock_out_minutes');
        return ($global !== null && $global !== '' && (int) $global > 0) ? (int) $global : null;
    }

    /**
     * Batas maksimal keterlambatan clock-out (menit setelah jam pulang shift).
     * Hierarki: hari -> template -> SystemSetting (default 240).
     * Nilai 0 = tanpa batas.
     */
    public function getMaxLateClockOutMinutes(): int
    {
        if ($this->max_late_clock_out_minutes !== null) {
            return (int) $this->max_late_clock_out_minutes;
        }

        if ($this->shiftTemplate && $this->shiftTemplate->max_late_clock_out_minutes !== null) {
            return (int) $this->shiftTemplate->max_late_clock_out_minutes;
        }

        return (int) SystemSetting::get('max_late_clock_out_minutes', 240);
    }

    public function getDayNameAttribute(): string
    {
        return match ($this->day_of_week) {
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Unknown',
        };
    }

    /**
     * Apakah jadwal ini lintas hari (pulang keesokan harinya)?
     * Contoh: satpam malam 22:00 - 06:00 (end <= start).
     */
    public function isOvernight(): bool
    {
        if ($this->is_day_off || empty($this->start_time) || empty($this->end_time)) {
            return false;
        }

        return substr((string) $this->end_time, 0, 5) <= substr((string) $this->start_time, 0, 5);
    }

    /**
     * Jam mulai shift pada tanggal dinas (duty date) tertentu.
     */
    public function getScheduledStartForDate(string $date): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse("{$date} {$this->start_time}");
    }

    /**
     * Jam selesai shift pada tanggal dinas tertentu.
     * Untuk shift lintas hari, otomatis +1 hari dari tanggal dinas.
     */
    public function getScheduledEndForDate(string $date): \Carbon\Carbon
    {
        $end = \Carbon\Carbon::parse("{$date} {$this->end_time}");
        if ($this->isOvernight()) {
            $end->addDay();
        }

        return $end;
    }
}
