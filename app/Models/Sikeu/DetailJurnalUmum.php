<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailJurnalUmum extends Model
{
    use HasFactory;

    protected $table = 'detail_jurnal_umum';

    protected $fillable = [
        'jurnal_id',
        'akun_id',
        'debet',
        'kredit',
        'keterangan',
    ];

    protected $casts = [
        'debet' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function jurnal()
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_id');
    }

    public function akun()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_id');
    }
}
