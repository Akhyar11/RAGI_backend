<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KetercapaianCpmkMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_ketercapaian_cpmk_mhs';

    protected $fillable = [
        'krs_detail_id',
        'cpmk_id',
        'skor_ketercapaian',
        'status_ketercapaian',
    ];

    protected $casts = [
        'skor_ketercapaian' => 'decimal:2',
    ];

    public function krsDetail()
    {
        return $this->belongsTo(KrsDetail::class, 'krs_detail_id');
    }

    public function cpmk()
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }
}
