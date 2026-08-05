<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifSpmb extends Model
{
    use HasFactory;

    protected $table = 'tarif_spmb';

    protected $fillable = [
        'jenis_biaya_id',
        'jalur_id',
        'gelombang_id',
        'nominal',
        'is_active',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }
}
