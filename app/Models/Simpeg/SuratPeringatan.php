<?php

namespace App\Models\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPeriode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratPeringatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_surat_peringatan';

    protected $fillable = [
        'pegawai_id',
        'gaji_pegawai_id',
        'presensi_periode_id',
        'nomor_sp',
        'jenis_sp',
        'judul',
        'periode_bulan_tahun',
        'total_terlambat',
        'batas_maksimal',
        'keterangan',
        'tanggal_terbit',
        'status',
    ];

    protected $casts = [
        'total_terlambat' => 'integer',
        'batas_maksimal' => 'integer',
        'tanggal_terbit' => 'date:Y-m-d',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function gajiPegawai(): BelongsTo
    {
        return $this->belongsTo(GajiPegawai::class, 'gaji_pegawai_id');
    }

    public function presensiPeriode(): BelongsTo
    {
        return $this->belongsTo(PresensiPeriode::class, 'presensi_periode_id');
    }
}
