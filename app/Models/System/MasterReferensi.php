<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterReferensi extends Model
{
    use HasFactory;

    protected $table = 'spmb_master_referensi';

    protected $fillable = [
        'tipe',
        'kode',
        'nama',
        'urutan',
        'is_active',
    ];
}
