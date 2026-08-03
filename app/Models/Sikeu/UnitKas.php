<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitKas extends Model
{
    use HasFactory;

    protected $table = 'unit_kas';

    protected $fillable = [
        'unit_kerja_id',
        'nama_kas',
        'saldo_awal',
        'saldo_saat_ini',
        'penanggung_jawab_id',
        'deskripsi',
        'status',
        'is_kabag_kas',
    ];

    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'saldo_saat_ini' => 'decimal:2',
        'status' => 'boolean',
        'is_kabag_kas' => 'boolean',
    ];

    public function pencairans()
    {
        return $this->hasMany(PengajuanPencairanKas::class, 'unit_kas_id');
    }

    public function transaksis()
    {
        return $this->hasMany(TransaksiKasUnit::class, 'unit_kas_id');
    }
}
