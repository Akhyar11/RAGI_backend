<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterTipeJalur extends Model
{
    use HasFactory;

    protected $table = 'master_tipe_jalur';

    protected $fillable = [
        'kode',
        'nama',
    ];
}
