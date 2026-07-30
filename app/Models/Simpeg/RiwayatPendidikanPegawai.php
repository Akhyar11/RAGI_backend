<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatPendidikanPegawai extends Model
{
    use HasFactory;

    protected $table = 'riwayat_pendidikan_pegawai';

    protected $fillable = [
        'pegawai_id',
        'jenjang',
        'nama_institusi',
        'program_studi',
        'bidang_ilmu',
        'tahun_masuk',
        'tahun_lulus',
        'nomor_ijazah',
        'file_ijazah',
        'is_pendidikan_terakhir',
    ];

    protected $casts = [
        'tahun_masuk' => 'integer',
        'tahun_lulus' => 'integer',
        'is_pendidikan_terakhir' => 'boolean',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
