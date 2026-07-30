<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'induk_id',
        'kode',
        'nama',
        'tipe',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(UnitKerja::class, 'induk_id');
    }

    public function children()
    {
        return $this->hasMany(UnitKerja::class, 'induk_id');
    }

    public function jabatan()
    {
        return $this->hasMany(Jabatan::class, 'unit_kerja_id');
    }

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class, 'unit_kerja_id');
    }
}
