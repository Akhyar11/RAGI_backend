<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifSpmb extends Model
{
    use HasFactory;

    protected $table = 'sikeu_tarif_spmb';

    protected $fillable = [
        'master_biaya_id',
        'jalur_id',
        'gelombang_id',
        'nominal',
        'is_active',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }
}
