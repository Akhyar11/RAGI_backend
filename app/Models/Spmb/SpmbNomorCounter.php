<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpmbNomorCounter extends Model
{
    use HasFactory;

    protected $table = 'spmb_nomor_counter';

    protected $fillable = [
        'tahun_akademik',
        'kode_prodi',
        'last_sequence',
    ];

    protected $casts = [
        'last_sequence' => 'integer',
    ];
}
