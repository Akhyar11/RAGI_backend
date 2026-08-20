<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RpsMingguan extends Model
{
    use HasFactory;

    protected $table = 'siakad_rps_mingguan';

    protected $fillable = [
        'rps_id',
        'minggu_ke',
        'kemampuan_akhir',
        'bahan_kajian',
        'bentuk_metode',
        'estimasi_waktu',
        'pengalaman_belajar',
        'indikator_penilaian',
        'bobot_penilaian',
    ];

    protected $casts = [
        'bobot_penilaian' => 'decimal:2',
    ];

    public function rps()
    {
        return $this->belongsTo(Rps::class, 'rps_id');
    }
}
