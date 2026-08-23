<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory;

    protected $table = 'sikeu_virtual_account';

    protected $fillable = [
        'tagihan_id',
        'va_number',
        'bank_kode',
        'bank_nama',
        'nominal',
        'expired_at',
        'status',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'expired_at' => 'datetime',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function pembayarans()
    {
        return $this->hasMany(Pembayaran::class, 'virtual_account_id');
    }
}
