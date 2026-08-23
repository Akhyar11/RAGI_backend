<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DendaTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_denda_tagihan';

    protected $fillable = [
        'tagihan_id',
        'tipe_denda',
        'nominal_denda',
        'tanggal_denda',
        'keterangan',
    ];

    protected $casts = [
        'nominal_denda' => 'decimal:2',
        'tanggal_denda' => 'date',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }
}
