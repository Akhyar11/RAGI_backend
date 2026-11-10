<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class H2hReversal extends Model
{
    protected $table = 'sikeu_h2h_reversals';

    protected $fillable = [
        'id_transaksi',
        'tanggal_transaksi',
        'tanggal_transaksi_asal',
        'nomor_pembayaran',
        'nomor_jurnal_pembukuan',
        'total_nominal',
        'kode_bank',
        'checksum',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'datetime',
        'tanggal_transaksi_asal' => 'datetime',
        'total_nominal' => 'decimal:2',
    ];
}
