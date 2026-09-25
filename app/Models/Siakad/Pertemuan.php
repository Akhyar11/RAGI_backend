<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class Pertemuan extends Model
{
    protected $table = 'siakad_pertemuan';

    protected $fillable = [
        'kelas_id',
        'pertemuan_ke',
        'tanggal',
        'materi',
        'catatan_pertemuan',
        'status_pertemuan',
        'token_absensi',
        'token_expired_at',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'token_expired_at' => 'datetime',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function absensi()
    {
        return $this->hasMany(AbsensiMahasiswa::class, 'pertemuan_id');
    }

    public function materiList()
    {
        return $this->hasMany(\App\Models\Lms\MateriPertemuan::class, 'pertemuan_id')->orderBy('urutan');
    }

    public function tugasList()
    {
        return $this->hasMany(\App\Models\Lms\Tugas::class, 'pertemuan_id');
    }

    public function izinAbsensiList()
    {
        return $this->hasMany(\App\Models\Lms\IzinAbsensi::class, 'pertemuan_id');
    }
}
