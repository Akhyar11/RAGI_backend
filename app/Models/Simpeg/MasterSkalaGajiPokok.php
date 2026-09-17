<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSkalaGajiPokok extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_skala_gaji_pokok';

    protected $fillable = [
        'nama_skala',
        'golongan',
        'masa_kerja_min_tahun',
        'masa_kerja_max_tahun',
        'nominal_gaji',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'masa_kerja_min_tahun' => 'integer',
        'masa_kerja_max_tahun' => 'integer',
        'nominal_gaji' => 'float',
        'is_active' => 'boolean',
    ];
}
