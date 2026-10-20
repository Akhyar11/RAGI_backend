<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaPkl extends Model
{
    protected $table = 'siakad_pa_pkl';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'dosen_id',
        'jenis',
        'instansi',
        'lokasi',
        'pembimbing_lapangan',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }
}
