<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCpmk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_sub_cpmk';

    protected $fillable = [
        'cpmk_id',
        'kode_sub_cpmk',
        'deskripsi',
        'indikator',
        'bobot_persentase',
    ];

    protected $casts = [
        'bobot_persentase' => 'decimal:2',
    ];

    public function cpmk()
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }

    public function komponenPenilaians()
    {
        return $this->hasMany(KomponenPenilaian::class, 'sub_cpmk_id');
    }
}
