<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CutiMahasiswa extends Model
{
    protected $table = 'siakad_cuti_mahasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'alasan',
        'file_surat',
        'status',
        'diproses_oleh',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
