<?php

namespace App\Models\Sinapra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterSatuan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_master_satuan';

    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
        'is_active',
        'urutan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];
}
