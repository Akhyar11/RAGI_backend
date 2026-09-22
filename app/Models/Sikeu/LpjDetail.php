<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LpjDetail extends Model
{
    use HasFactory;

    protected $table = 'sikeu_lpj_detail';

    protected $fillable = [
        'lpj_id',
        'tipe',
        'keterangan',
        'qty',
        'satuan',
        'harga_satuan',
        'subtotal',
        'file_bukti_path',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function lpj()
    {
        return $this->belongsTo(LaporanBuktiPelaksanaan::class, 'lpj_id');
    }
}
