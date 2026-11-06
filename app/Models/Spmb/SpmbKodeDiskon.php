<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpmbKodeDiskon extends Model
{
    use SoftDeletes;

    protected $table = 'spmb_kode_diskon';

    protected $fillable = [
        'kode',
        'nama',
        'tipe_diskon',
        'nilai',
        'maksimum_potongan',
        'batas_penggunaan',
        'berlaku_mulai',
        'berlaku_sampai',
        'is_active',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'maksimum_potongan' => 'decimal:2',
        'batas_penggunaan' => 'integer',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
        'is_active' => 'boolean',
    ];

    public function penggunaan()
    {
        return $this->hasMany(SpmbKodeDiskonPenggunaan::class, 'kode_diskon_id');
    }
}
