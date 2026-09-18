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

    /**
     * Pegawai yang memiliki lokasi ini sebagai lokasi tambahan (multi-lokasi).
     */
    public function additionalEmployees()
    {
        return $this->belongsToMany(
            Pegawai::class,
            'simpeg_pegawai_office_locations',
            'office_location_id',
            'pegawai_id'
        )->withTimestamps();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'office_location_id');
    }
}
