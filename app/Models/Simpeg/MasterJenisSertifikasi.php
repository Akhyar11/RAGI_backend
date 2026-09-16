<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterJenisSertifikasi extends Model
{
    protected $table = 'simpeg_master_jenis_sertifikasi';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sertifikasiDosen()
    {
        return $this->hasMany(SertifikasiDosen::class, 'jenis_sertifikasi_id');
    }
}
