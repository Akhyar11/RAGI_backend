<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;

class MasterTingkatKegiatan extends Model
{
    protected $table = 'simpeg_master_tingkat_kegiatan';

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
        return $this->hasMany(RiwayatPelatihan::class, 'tingkat_id');
    }
}
