<?php

namespace App\Models;

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
        'applies_national_holidays',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_day_off' => 'boolean',
        'late_tolerance_minutes' => 'integer',
        'early_leave_tolerance_minutes' => 'integer',
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
}
