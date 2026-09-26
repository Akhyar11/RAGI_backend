<?php

namespace App\Models;

use App\Models\Simpeg\Pegawai;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    use HasFactory;

    protected $table = 'simpeg_shift_templates';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
        'max_early_clock_in_minutes',
        'max_late_clock_in_minutes',
        'max_early_clock_out_minutes',
        'max_late_clock_out_minutes',
        'applies_national_holidays',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'late_tolerance_minutes' => 'integer',
        'early_leave_tolerance_minutes' => 'integer',
        'max_early_clock_in_minutes' => 'integer',
        'max_late_clock_in_minutes' => 'integer',
        'max_early_clock_out_minutes' => 'integer',
        'max_late_clock_out_minutes' => 'integer',
        'applies_national_holidays' => 'boolean',
    ];

    public function days()
    {
        return $this->hasMany(ShiftScheduleDay::class, 'shift_template_id')->orderBy('day_of_week');
    }

    public function employees()
    {
        return $this->hasMany(Pegawai::class, 'shift_template_id');
    }

    public function getScheduleForDay(int $dayOfWeek): ?ShiftScheduleDay
    {
        return $this->days()->where('day_of_week', $dayOfWeek)->first();
    }

    public function getMaxEarlyClockOutMinutes(): ?int
    {
        if ($this->max_early_clock_out_minutes !== null) {
            return (int) $this->max_early_clock_out_minutes;
        }

        $global = SystemSetting::get('max_early_clock_out_minutes');
        return ($global !== null && $global !== '' && (int) $global > 0) ? (int) $global : null;
    }

    public function getMaxLateClockOutMinutes(): int
    {
        if ($this->max_late_clock_out_minutes !== null) {
            return (int) $this->max_late_clock_out_minutes;
        }

        return (int) SystemSetting::get('max_late_clock_out_minutes', 240);
    }
}
