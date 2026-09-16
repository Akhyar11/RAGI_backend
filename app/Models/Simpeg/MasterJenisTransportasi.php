<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterJenisTransportasi extends Model
{
    protected $table = 'simpeg_master_jenis_transportasi';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function suratTugas()
    {
        return $this->hasMany(SuratTugas::class, 'jenis_transportasi_id');
    }
}
