<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_detail_tagihan';

    protected $fillable = [
        'tagihan_id',
        'master_biaya_id',
        'nominal',
        'potongan',
        'nominal_bersih',
        'keterangan',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'potongan' => 'decimal:2',
        'nominal_bersih' => 'decimal:2',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }
}
