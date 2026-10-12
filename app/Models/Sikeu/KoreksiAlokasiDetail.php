<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoreksiAlokasiDetail extends Model
{
    use HasFactory;

    protected $table = 'sikeu_koreksi_alokasi_detail';

    protected $fillable = [
        'koreksi_alokasi_id',
        'from_tagihan_id',
        'from_detail_tagihan_id',
        'to_tagihan_id',
        'to_detail_tagihan_id',
        'nominal_dialihkan',
        'keterangan',
    ];

    protected $casts = [
        'nominal_dialihkan' => 'decimal:2',
    ];

    public function koreksi()
    {
        return $this->belongsTo(KoreksiAlokasi::class, 'koreksi_alokasi_id');
    }

    public function fromTagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'from_tagihan_id');
    }

    public function toTagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'to_tagihan_id');
    }

    public function fromDetail()
    {
        return $this->belongsTo(DetailTagihan::class, 'from_detail_tagihan_id');
    }

    public function toDetail()
    {
        return $this->belongsTo(DetailTagihan::class, 'to_detail_tagihan_id');
    }
}
