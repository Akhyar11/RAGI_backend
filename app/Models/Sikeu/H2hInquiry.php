<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class H2hInquiry extends Model
{
    protected $table = 'sikeu_h2h_inquiries';

    protected $fillable = [
        'id_transaksi',
        'tanggal_transaksi',
        'id_tagihan',
        'nomor_pembayaran',
        'kode_bank',
        'kode_biller',
        'kode_channel',
        'kode_terminal',
        'checksum',
        'paid_at',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'datetime',
        'paid_at' => 'datetime',
    ];
}
