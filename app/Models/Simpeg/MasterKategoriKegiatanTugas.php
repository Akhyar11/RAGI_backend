<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterKategoriKegiatanTugas extends Model
{
    protected $table = 'simpeg_master_kategori_kegiatan_tugas';

    protected $fillable = [
        'nama',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function suratTugas()
    {
        return $this->hasMany(SuratTugas::class, 'kategori_kegiatan_id');
    }
}
