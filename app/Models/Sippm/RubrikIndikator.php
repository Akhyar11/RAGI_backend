<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RubrikIndikator extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_rubrik_indikator';

    protected $fillable = [
        'tipe_reviewer',
        'nama_indikator',
        'deskripsi',
        'bobot',
        'skor_minimal_default',
        'is_active',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'skor_minimal_default' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
