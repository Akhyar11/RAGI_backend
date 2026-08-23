<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanKegiatan extends Model
{
    use HasFactory;

    protected $table = 'sippm_laporan_kegiatan';

    protected $fillable = [
        'kontrak_id',
        'jenis_laporan',
        'file_laporan',
        'file_logbook',
        'file_penggunaan_anggaran',
        'persentase_capaian',
        'status_verifikasi',
        'catatan_lppm',
        'submitted_at',
    ];

    protected $casts = [
        'persentase_capaian' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function kontrak()
    {
        return $this->belongsTo(KontrakKegiatan::class, 'kontrak_id');
    }
}
