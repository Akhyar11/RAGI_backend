<?php

namespace App\Models\Simpeg;

use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PengeluaranKampus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'total_honor_sks',
        'total_sks_diampu',
        'total_tunjangan_fungsional',
        'jumlah_hari_hadir_tepat_waktu',
        'total_tunjangan',
        'total_potongan',
        'total_pph21',
        'total_bpjs',
        'gaji_bersih',
        'status_transfer',
        'jurnal_id',
        'pengeluaran_kampus_id',
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
        'total_honor_sks' => 'float',
        'total_sks_diampu' => 'float',
        'total_tunjangan_fungsional' => 'float',
        'total_tunjangan' => 'float',
        'total_potongan' => 'float',
        'total_pph21' => 'float',
        'total_bpjs' => 'float',
        'gaji_bersih' => 'float',
        'jumlah_hari_hadir_tepat_waktu' => 'integer',
        'submitted_at' => 'datetime',
        'tanggal_transfer' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(GajiDetail::class, 'gaji_pegawai_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_id');
    }

    public function pengeluaranKampus(): BelongsTo
    {
        return $this->belongsTo(PengeluaranKampus::class, 'pengeluaran_kampus_id');
    }
}
