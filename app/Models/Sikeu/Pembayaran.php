<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tagihan_id',
        'virtual_account_id',
        'kode_transaksi',
        'jumlah_bayar',
        'waktu_bayar',
        'channel_bayar',
        'bank_pengirim',
        'status',
        'diverifikasi_oleh',
    ];

    protected $casts = [
        'jumlah_bayar' => 'decimal:2',
        'waktu_bayar' => 'datetime',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function virtualAccount()
    {
        return $this->belongsTo(VirtualAccount::class, 'virtual_account_id');
    }
}
