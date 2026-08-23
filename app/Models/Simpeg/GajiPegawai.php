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
        'total_tunjangan',
        'total_potongan',
        'gaji_bersih',
        'status_transfer',
        'tanggal_transfer',
        'nomor_rekening',
        'bank_nama',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
