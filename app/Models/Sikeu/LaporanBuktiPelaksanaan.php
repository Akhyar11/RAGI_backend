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
        'pengajuan_id',
        'nomor_bukti',
        'tanggal_pelaksanaan',
        'total_realisasi',
        'nominal_dicairkan',
        'sisa_nominal',
        'metode_sisa',
        'file_nota_kuitansi',
        'bukti_pengembalian_path',
        'nomor_rekening_tujuan',
        'rincian_keterangan',
        'status_verifikasi',
        'diverifikasi_oleh',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'tanggal_pelaksanaan' => 'date',
        'total_realisasi' => 'decimal:2',
        'nominal_dicairkan' => 'decimal:2',
        'sisa_nominal' => 'decimal:2',
    ];

    public function pengajuan()
    {
        return $this->belongsTo(PengajuanPencairanKas::class, 'pengajuan_id');
    }

    public function details()
    {
        return $this->hasMany(LpjDetail::class, 'lpj_id');
    }
}
