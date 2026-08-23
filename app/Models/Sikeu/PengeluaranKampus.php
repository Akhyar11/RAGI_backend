<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengeluaranKampus extends Model
{
    use HasFactory;

    protected $table = 'sikeu_pengeluaran_kampus';

    protected $fillable = [
        'nomor_transaksi',
        'kategori',
        'akun_beban_id',
        'akun_kas_id',
        'nominal',
        'keterangan',
        'tanggal_transaksi',
        'nama_vendor',
        'npwp_vendor',
        'jenis_pajak',
        'tarif_pajak_persen',
        'nominal_pajak',
        'net_dibayarkan',
        'status_pembayaran',
        'file_bukti_bayar',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tarif_pajak_persen' => 'decimal:2',
        'nominal_pajak' => 'decimal:2',
        'net_dibayarkan' => 'decimal:2',
        'tanggal_transaksi' => 'date',
    ];

    public function akunBeban()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_beban_id');
    }

    public function akunKas()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_kas_id');
    }
}
