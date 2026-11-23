<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\JalurMasuk;

class SpmbKodeDiskon extends Model
{
    use SoftDeletes;

    public const TIPE_PERSEN = 'persen';

    public const TIPE_NOMINAL = 'nominal';

    public const TIPE_TOTAL_TETAP = 'total_tetap';

    public const TIPE_DISKON = [self::TIPE_PERSEN, self::TIPE_NOMINAL, self::TIPE_TOTAL_TETAP];

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

    public function jalurMasuk()
    {
        return $this->belongsToMany(JalurMasuk::class, 'spmb_kode_diskon_jalur_masuk', 'kode_diskon_id', 'jalur_masuk_id')
            ->withTimestamps();
    }

}
