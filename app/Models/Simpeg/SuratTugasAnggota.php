<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class SuratTugasAnggota extends Model
{
    protected $table = 'simpeg_surat_tugas_anggota';

    protected $fillable = [
        'surat_tugas_id',
        'pegawai_id',
        'peran',
    ];

    public function suratTugas()
    {
        return $this->belongsTo(SuratTugas::class, 'surat_tugas_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
