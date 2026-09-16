<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IzinJamKerja extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_izin_jam_kerja';

    protected $fillable = [
        'pegawai_id',
        'master_jenis_izin_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'alasan',
        'file_bukti',
        'status',
        'catatan_approval',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisIzin()
    {
        return $this->belongsTo(MasterJenisIzinJamKerja::class, 'master_jenis_izin_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
