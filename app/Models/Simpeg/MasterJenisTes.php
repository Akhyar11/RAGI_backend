<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterJenisTes extends Model
{
    protected $table = 'simpeg_master_jenis_tes';

    protected $fillable = [
        'nama',
        'kode',
        'kategori',
        'skor_min',
        'skor_max',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'skor_min' => 'float',
        'skor_max' => 'float',
    ];

    public function riwayatTes()
    {
        return $this->hasMany(RiwayatTes::class, 'jenis_tes_id');
    }
}
