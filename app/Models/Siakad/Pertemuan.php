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
        'jam_mulai',
        'jam_selesai',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function absensi()
    {
        return $this->hasMany(AbsensiMahasiswa::class, 'pertemuan_id');
    }
}
