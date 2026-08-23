<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_jabatan';

    protected $fillable = [
        'unit_kerja_id',
        'nama',
        'tipe',
        'level_jabatan',
        'is_active',
    ];

    protected $casts = [
        'level_jabatan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function riwayatJabatan()
    {
        return $this->hasMany(RiwayatJabatan::class, 'jabatan_id');
    }
}
