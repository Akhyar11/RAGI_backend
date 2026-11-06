<?php

namespace App\Models\Spmb;

use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Database\Eloquent\Model;

class SpmbKodeDiskonPenggunaan extends Model
{
    protected $table = 'spmb_kode_diskon_penggunaan';

    protected $fillable = [
        'kode_diskon_id',
        'pendaftaran_id',
        'kode_diskon',
        'subtotal_komponen',
        'nominal_diskon',
        'tagihan_id',
        'digunakan_at',
    ];

    protected $casts = [
        'subtotal_komponen' => 'decimal:2',
        'nominal_diskon' => 'decimal:2',
        'digunakan_at' => 'datetime',
    ];

    public function kodeDiskon()
    {
        return $this->belongsTo(SpmbKodeDiskon::class, 'kode_diskon_id')->withTrashed();
    }

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }
}
