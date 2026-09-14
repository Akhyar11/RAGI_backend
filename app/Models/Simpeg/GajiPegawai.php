<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GajiPegawai extends Model
{
    use HasFactory;

    protected $table = 'simpeg_gaji_pegawai';

    protected $fillable = [
        'pegawai_id',
        'periode_bulan_tahun',
        'gaji_pokok',
        'tunjangan_tetap',
        'total_biaya_transport',
        'jumlah_hari_hadir_tepat_waktu',
        'total_tunjangan',
        'total_potongan',
        'gaji_bersih',
        'status_transfer',
        'tanggal_transfer',
        'submitted_at',
        'nomor_rekening',
        'bank_nama',
        'catatan',
    ];

    protected $casts = [
        'gaji_pokok' => 'float',
        'tunjangan_tetap' => 'float',
        'total_biaya_transport' => 'float',
        'total_tunjangan' => 'float',
        'total_potongan' => 'float',
        'gaji_bersih' => 'float',
        'jumlah_hari_hadir_tepat_waktu' => 'integer',
        'submitted_at' => 'datetime',
        'tanggal_transfer' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
