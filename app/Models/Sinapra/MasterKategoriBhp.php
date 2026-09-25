<?php

namespace App\Models\Sinapra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKategoriBhp extends Model
{
    use HasFactory;

    protected $table = 'sinapra_master_kategori_bhp';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'is_active',
        'urutan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function labBhp()
    {
        return $this->hasMany(\App\Models\LabBhp::class, 'kategori_bhp_id');
    }
}
