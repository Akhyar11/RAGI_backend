<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitKas extends Model
{
    use HasFactory;

    protected $table = 'sikeu_unit_kas';

    protected $fillable = [
        'unit_kerja_id',
        'fakultas_id',
        'akun_keuangan_id',
        'nama_kas',
        'tipe_kas',
        'kanal',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'penanggung_jawab',
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

    public function akunKeuangan()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_keuangan_id');
    }

    public function transaksis()
    {
        return $this->hasMany(TransaksiKasUnit::class, 'unit_kas_id');
    }

    public function fakultas()
    {
        return $this->belongsTo(\App\Models\Siakad\Fakultas::class, 'fakultas_id');
    }

    public function penanggungJawab()
    {
        return $this->belongsTo(\App\Models\User::class, 'penanggung_jawab_id');
    }

    public function kasKecilTransaksis()
    {
        return $this->hasMany(KasKecilTransaksi::class, 'unit_kas_id');
    }

    public function kasKecilPengajuans()
    {
        return $this->hasMany(KasKecilPengajuan::class, 'unit_kas_id');
    }
}
