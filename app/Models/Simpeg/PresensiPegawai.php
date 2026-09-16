<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiPegawai extends Model
{
    use HasFactory;

    protected $table = 'simpeg_presensi_pegawai';

    protected $fillable = [
        'presensi_periode_id',
        'pegawai_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'status_kehadiran',
        'lat_long',
        'foto_presensi',
        'catatan',
        'is_approved_by_admin',
        'approved_by',
        'status',
        'approved_at',
        'notes',
        'source',
        'device_id',
        'device_ip',
        'early_leave_minutes',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PresensiPeriode::class, 'presensi_periode_id');
    }
}
