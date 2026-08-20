<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MataKuliah extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_mata_kuliah';

    protected $fillable = [
        'kurikulum_id',
        'kode_mk',
        'nama',
        'sks_teori',
        'sks_praktik',
        'total_sks',
        'semester_anjuran',
        'tipe',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sks_teori' => 'integer',
        'sks_praktik' => 'integer',
        'total_sks' => 'integer',
        'semester_anjuran' => 'integer',
    ];

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'mata_kuliah_id');
    }

    public function prasyarats()
    {
        return $this->hasMany(PrasyaratMk::class, 'mata_kuliah_id');
    }

    public function cpmks()
    {
        return $this->hasMany(Cpmk::class, 'mata_kuliah_id');
    }
}
