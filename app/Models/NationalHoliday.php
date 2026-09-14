<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    use HasFactory;

    protected $table = 'simpeg_national_holidays';

    protected $fillable = [
        'holiday_date',
        'name',
        'is_mass_leave',
        'description',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
        'is_mass_leave' => 'boolean',
    ];

    /**
     * Memeriksa apakah tanggal tertentu adalah hari libur nasional atau cuti bersama
     */
    public static function isHoliday(mixed $date): ?self
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $dateStr = $carbon->toDateString();

        return self::where('holiday_date', $dateStr)->first();
    }
}
