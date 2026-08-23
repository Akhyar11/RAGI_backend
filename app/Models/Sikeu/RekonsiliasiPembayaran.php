<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekonsiliasiPembayaran extends Model
{
    use HasFactory;

    protected $table = 'sikeu_rekonsiliasi_pembayaran';

    protected $fillable = [
        'tanggal_rekonsiliasi',
        'bank_kode',
        'total_transaksi',
        'total_nominal_sistem',
        'total_nominal_bank',
        'selisih',
        'status',
        'file_laporan_bank',
        'diproses_oleh',
    ];

    protected $casts = [
        'tanggal_rekonsiliasi' => 'date',
        'total_nominal_sistem' => 'decimal:2',
        'total_nominal_bank' => 'decimal:2',
        'selisih' => 'decimal:2',
    ];
}
