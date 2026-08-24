<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotonganTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_potongan_tagihan';

    protected $fillable = [
        'tagihan_id',

        'tipe',
        'nominal_potongan',
        'keterangan',
        'diinput_oleh',
    ];

    protected $casts = [
        'nominal_potongan' => 'decimal:2',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }


}
