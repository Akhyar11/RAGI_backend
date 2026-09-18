<?php

namespace App\Models;

use App\Models\Simpeg\Pegawai;
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
        'applies_national_holidays',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'late_tolerance_minutes' => 'integer',
        'early_leave_tolerance_minutes' => 'integer',
        'max_early_clock_in_minutes' => 'integer',
        'max_late_clock_in_minutes' => 'integer',
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
}
