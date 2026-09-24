<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahunAkademik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_tahun_akademik';

    protected $fillable = [
        'kode',
        'nama',
        'tahun_mulai',
        'tahun_selesai',
        'is_active',
        'mode_penilaian',
    ];

    protected $casts = [
        'tahun_mulai' => 'integer',
        'tahun_selesai' => 'integer',
        'is_active' => 'boolean',
    ];

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'tahun_akademik_id');
    }

    public function krs()
    {
        return $this->hasMany(Krs::class, 'tahun_akademik_id');
    }

    public function khs()
    {
        return $this->hasMany(Khs::class, 'tahun_akademik_id');
    }

    public function dosenPenugasan()
    {
        return $this->hasMany(DosenPenugasan::class, 'tahun_akademik_id');
    }
}
