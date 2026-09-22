<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelulusan extends Model
{
    protected $table = 'siakad_kelulusan';

    protected $fillable = [
        'mahasiswa_id', 'tahun_akademik_id', 'tanggal_sidang',
        'ipk_akhir', 'total_sks', 'masa_studi_semester',
        'predikat', 'nomor_ijazah', 'tanggal_ijazah',
    ];

    protected $casts = [
        'tanggal_sidang' => 'date', 'tanggal_ijazah' => 'date',
        'ipk_akhir' => 'decimal:2',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
