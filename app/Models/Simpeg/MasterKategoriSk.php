<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterKategoriSk extends Model
{
    protected $table = 'simpeg_master_kategori_sk';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function skPegawai()
    {
        return $this->hasMany(SkPegawai::class, 'kategori_sk_id');
    }
}
