<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanBuktiPelaksanaan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_laporan_bukti_pelaksanaan';

    protected $fillable = [
        'sumber_tipe',
        'sumber_id',
        'nomor_bukti',
        'tanggal_pelaksanaan',
        'total_realisasi',
        'file_nota_kuitansi',
        'rincian_keterangan',
        'status_verifikasi',
        'diverifikasi_oleh',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_pelaksanaan' => 'date',
        'total_realisasi' => 'decimal:2',
    ];
}
