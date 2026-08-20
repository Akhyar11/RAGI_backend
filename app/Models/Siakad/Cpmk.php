<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cpmk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_cpmk';

    protected $fillable = [
        'mata_kuliah_id',
        'cpl_id',
        'kode_cpmk',
        'deskripsi',
        'bobot_persentase',
    ];

    protected $casts = [
        'bobot_persentase' => 'decimal:2',
    ];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function cpl()
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }

    public function subCpmks()
    {
        return $this->hasMany(SubCpmk::class, 'cpmk_id');
    }

    public function komponenPenilaians()
    {
        return $this->hasMany(KomponenPenilaian::class, 'cpmk_id');
    }

    public function mappedCpls()
    {
        return $this->belongsToMany(Cpl::class, 'siakad_pemetaan_cpl_cpmk', 'cpmk_id', 'cpl_id')
            ->withPivot('bobot_kontribusi')
            ->withTimestamps();
    }
}
