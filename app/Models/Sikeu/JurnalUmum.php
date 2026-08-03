<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    use HasFactory;

    protected $table = 'jurnal_umum';

    protected $fillable = [
        'nomor_jurnal',
        'tanggal_jurnal',
        'periode_id',
        'jenis_sumber',
        'referensi_id',
        'keterangan',
        'status_posting',
        'total_debet',
        'total_kredit',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'tanggal_jurnal' => 'date',
        'total_debet' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function details()
    {
        return $this->hasMany(DetailJurnalUmum::class, 'jurnal_id');
    }
}
