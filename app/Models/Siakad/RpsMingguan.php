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
        'sub_cpmk_id',
        'sub_cpmk_ids',
        'komponen_evaluasi_id',
        'minggu_ke',
        'jenis_pertemuan',
        'kemampuan_akhir',
        'bahan_kajian',
        'bentuk_metode',
        'bentuk_luring',
        'bentuk_daring',
        'aktivitas_luring',
        'aktivitas_daring',
        'estimasi_waktu',
        'pengalaman_belajar',
        'penugasan_mahasiswa',
        'indikator_penilaian',
        'kriteria_penilaian_id',
        'teknik_penilaian',
        'kriteria_teknik',
        'bobot_penilaian',
    ];

    protected $casts = [
        'sub_cpmk_ids' => 'array',
        'aktivitas_luring' => 'array',
        'aktivitas_daring' => 'array',
        'bobot_penilaian' => 'decimal:2',
    ];

    public function rps()
    {
        return $this->belongsTo(Rps::class, 'rps_id');
    }

    public function subCpmk()
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }

    public function komponenEvaluasi()
    {
        return $this->belongsTo(RpsReferensi::class, 'komponen_evaluasi_id');
    }

    public function kriteriaPenilaian()
    {
        return $this->belongsTo(RpsReferensi::class, 'kriteria_penilaian_id');
    }
}
