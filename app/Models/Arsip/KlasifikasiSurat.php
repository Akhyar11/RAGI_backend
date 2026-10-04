<?php

namespace App\Models\Arsip;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KlasifikasiSurat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'core_arsip_klasifikasi';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
