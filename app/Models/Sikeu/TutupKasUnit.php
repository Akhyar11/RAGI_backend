<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutupKasUnit extends Model
{
    use HasFactory;

    protected $table = 'sikeu_tutup_kas';

    protected $fillable = [
        'unit_kas_id',
        'tanggal',
        'saldo_awal',
        'saldo_masuk',
        'saldo_keluar',
        'saldo_akhir',
        'is_locked',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'saldo_awal' => 'decimal:2',
        'saldo_masuk' => 'decimal:2',
        'saldo_keluar' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }
}
