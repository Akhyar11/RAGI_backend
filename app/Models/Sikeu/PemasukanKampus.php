<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemasukanKampus extends Model
{
    use HasFactory;

    protected $table = 'pemasukan_kampus';

    protected $fillable = [
        'nomor_transaksi',
        'sumber_pemasukan',
        'unit_kas_id',
        'akun_pendapatan_id',
        'nominal',
        'tanggal_terima',
        'nama_donor_instansi',
        'nomor_kontrak_ref',
        'file_bukti_transfer',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_terima' => 'date',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function akunPendapatan()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_pendapatan_id');
    }
}
