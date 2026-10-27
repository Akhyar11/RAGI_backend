<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoAwalDetail extends Model
{
    use HasFactory;

    protected $table = 'sikeu_saldo_awal_detail';

    protected $fillable = [
        'saldo_awal_id',
        'akun_id',
        'debet',
        'kredit',
        'keterangan',
    ];

    protected $casts = [
        'debet' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function saldoAwal()
    {
        return $this->belongsTo(SaldoAwal::class, 'saldo_awal_id');
    }

    public function akun()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_id');
    }
}
