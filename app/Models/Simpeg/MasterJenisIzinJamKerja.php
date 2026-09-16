<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterJenisIzinJamKerja extends Model
{
    protected $table = 'simpeg_master_jenis_izin_jam_kerja';

    protected $fillable = [
        'nama',
        'kode',
        'tipe_potongan',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function izinJamKerja()
    {
        return $this->hasMany(IzinJamKerja::class, 'master_jenis_izin_id');
    }
}
