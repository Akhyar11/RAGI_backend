<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaLaporan extends Model
{
    protected $table = 'siakad_pa_laporan';

    protected $fillable = [
        'dosen_id',
        'tahun_akademik_id',
        'tanggal',
        'kelas',
        'mhs_aktif',
        'mhs_nonaktif',
        'mhs_cuti',
        'mhs_keluar',
        'kondisi_mahasiswa',
        'penanganan_mahasiswa',
        'kesimpulan',
        'rekomendasi',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'mhs_aktif' => 'integer',
        'mhs_nonaktif' => 'integer',
        'mhs_cuti' => 'integer',
        'mhs_keluar' => 'integer',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }
}
