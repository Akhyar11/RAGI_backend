<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTagihan extends Model
{
    use HasFactory;

    protected $table = 'detail_tagihan';

    protected $fillable = [
        'tagihan_id',
        'jenis_biaya_id',
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

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }
}
