<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaCatatan extends Model
{
    protected $table = 'siakad_pa_catatan';

    protected $fillable = [
        'dosen_id',
        'mahasiswa_id',
        'tahun_akademik_id',
        'tanggal_bimbingan',
        'kategori',
        'isi',
        'kesimpulan',
        'butuh_penanganan_khusus',
        'status_tindak_lanjut',
        'dibuat_oleh',
    ];

    protected $casts = [
        'butuh_penanganan_khusus' => 'boolean',
        'tanggal_bimbingan' => 'date',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
