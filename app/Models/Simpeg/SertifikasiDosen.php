<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SertifikasiDosen extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_sertifikasi_dosen';

    protected $fillable = [
        'pegawai_id',
        'jenis_sertifikasi_id',
        'nama_sertifikat',
        'bidang_studi',
        'nomor_registrasi',
        'nomor_sk',
        'tahun_sertifikasi',
        'penyelenggara',
        'file_path',
        'tautan',
    ];

    protected $casts = [
        'tahun_sertifikasi' => 'integer',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisSertifikasi()
    {
        return $this->belongsTo(MasterJenisSertifikasi::class, 'jenis_sertifikasi_id');
    }
}
