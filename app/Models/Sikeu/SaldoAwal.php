<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoAwal extends Model
{
    use HasFactory;

    protected $table = 'sikeu_saldo_awal';

    protected $fillable = [
        'tanggal',
        'periode_id',
        'status',
        'keterangan',
        'total_debet',
        'total_kredit',
        'nominal_penyeimbang',
        'akun_penyeimbang_id',
        'jurnal_id',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_debet' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'nominal_penyeimbang' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(SaldoAwalDetail::class, 'saldo_awal_id');
    }

    public function periode()
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function akunPenyeimbang()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_penyeimbang_id');
    }

    public function jurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_id');
    }
}
