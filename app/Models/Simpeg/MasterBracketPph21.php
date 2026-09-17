<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBracketPph21 extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_bracket_pph21';

    protected $fillable = [
        'kategori',
        'penghasilan_bruto_min',
        'penghasilan_bruto_max',
        'tarif_persen',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'penghasilan_bruto_min' => 'float',
        'penghasilan_bruto_max' => 'float',
        'tarif_persen' => 'float',
        'is_active' => 'boolean',
    ];
}
