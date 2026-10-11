<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiPeriode extends Model
{
    use HasFactory;

    protected $table = 'simpeg_presensi_periode';

    protected $fillable = [
        'nama_periode',
        'tanggal_awal',
        'tanggal_akhir',
        'bulan_tahun',
        'total_record',
        'is_locked',
        'status_pembayaran',
        'locked_at',
        'locked_by',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_awal' => 'date:Y-m-d',
        'tanggal_akhir' => 'date:Y-m-d',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function presensiPegawai()
    {
        return $this->hasMany(PresensiPegawai::class, 'presensi_periode_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by');
    }
}
