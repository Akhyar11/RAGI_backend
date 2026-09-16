<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterJenisPelatihan extends Model
{
    protected $table = 'simpeg_master_jenis_pelatihan';

    protected $fillable = [
        'nama',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function riwayatPelatihan()
    {
        return $this->hasMany(RiwayatPelatihan::class, 'jenis_pelatihan_id');
    }
}
