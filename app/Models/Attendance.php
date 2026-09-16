<?php

namespace App\Models;

use App\Models\Simpeg\Pegawai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'simpeg_presensi_pegawai';

    protected $fillable = [
        'presensi_periode_id',
        'pegawai_id',
        'employee_id',
        'office_location_id',
        'tanggal',
        'attendance_date',
        'jam_masuk',
        'jam_keluar',
        'clock_in',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_distance_meters',
        'clock_in_accuracy',
        'clock_in_face_score',
        'clock_in_is_mock_location',
        'clock_out',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_distance_meters',
        'clock_out_accuracy',
        'clock_out_face_score',
        'clock_out_is_mock_location',
        'status',
        'status_kehadiran',
        'late_minutes',
        'rejection_reason',
        'notes',
        'catatan',
        'lat_long',
        'foto_presensi',
        'is_approved_by_admin',
        'approved_by',
        'approved_at',
        'source',
        'device_id',
        'device_ip',
        'early_leave_minutes',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'attendance_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'clock_in_latitude' => 'float',
        'clock_in_longitude' => 'float',
        'clock_in_distance_meters' => 'float',
        'clock_in_accuracy' => 'float',
        'clock_in_face_score' => 'float',
        'clock_in_is_mock_location' => 'boolean',
        'clock_out_latitude' => 'float',
        'clock_out_longitude' => 'float',
        'clock_out_distance_meters' => 'float',
        'clock_out_accuracy' => 'float',
        'clock_out_face_score' => 'float',
        'clock_out_is_mock_location' => 'boolean',
        'is_approved_by_admin' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Sinkronisasi dwiarah employee_id <-> pegawai_id
    public function getEmployeeIdAttribute(): ?int
    {
        return $this->attributes['pegawai_id'] ?? null;
    }

    public function setEmployeeIdAttribute($value): void
    {
        $this->attributes['pegawai_id'] = $value;
    }

    // Sinkronisasi dwiarah attendance_date <-> tanggal
    public function getAttendanceDateAttribute(): ?string
    {
        return $this->attributes['tanggal'] ?? null;
    }

    public function setAttendanceDateAttribute($value): void
    {
        $this->attributes['tanggal'] = $value;
    }

    // Sinkronisasi dwiarah status <-> status_kehadiran
    public function getStatusAttribute(): ?string
    {
        return $this->attributes['status'] ?? $this->attributes['status_kehadiran'] ?? 'hadir';
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
        $this->attributes['status_kehadiran'] = $value;
    }

    // Sinkronisasi dwiarah notes <-> catatan
    public function getNotesAttribute(): ?string
    {
        return $this->attributes['notes'] ?? $this->attributes['catatan'] ?? null;
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $value;
        $this->attributes['catatan'] = $value;
    }

    public function employee()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function officeLocation()
    {
        return $this->belongsTo(OfficeLocation::class, 'office_location_id');
    }

    public function periode()
    {
        return $this->belongsTo(\App\Models\Simpeg\PresensiPeriode::class, 'presensi_periode_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getLateFormattedAttribute(): string
    {
        if (!$this->late_minutes || $this->late_minutes <= 0) {
            return '-';
        }

        $hours = intdiv($this->late_minutes, 60);
        $mins = $this->late_minutes % 60;
        return sprintf('%d.%02d', $hours, $mins);
    }
}
