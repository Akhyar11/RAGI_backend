<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanItem extends Model
{
    use HasFactory;

    protected $table = 'sikeu_pengajuan_item';

    protected $fillable = [
        'pengajuan_id',
        'nama_barang',
        'qty',
        'satuan',
        'harga_satuan',
        'subtotal',
        'keterangan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pengajuan()
    {
        return $this->belongsTo(PengajuanPencairanKas::class, 'pengajuan_id');
    }
}
