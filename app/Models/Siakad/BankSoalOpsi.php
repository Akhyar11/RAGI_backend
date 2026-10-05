<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class BankSoalOpsi extends Model
{
    protected $table = 'siakad_bank_soal_opsi';

    protected $fillable = [
        'bank_soal_id',
        'teks',
        'gambar_path',
        'is_benar',
        'urutan',
    ];

    protected $casts = [
        'is_benar' => 'boolean',
        'urutan' => 'integer',
    ];

    public function soal()
    {
        return $this->belongsTo(BankSoal::class, 'bank_soal_id');
    }
}
