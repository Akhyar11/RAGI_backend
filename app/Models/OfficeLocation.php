<?php

namespace App\Models;

use App\Models\Simpeg\Pegawai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    use HasFactory;

    protected $table = 'simpeg_office_locations';

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Pegawai::class, 'office_location_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'office_location_id');
    }
}
