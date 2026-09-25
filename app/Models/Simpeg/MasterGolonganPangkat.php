<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterGolonganPangkat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_master_golongan_pangkat';

    protected $fillable = [
        'kode',
        'nama',
        'pangkat',
        'ruang',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function jabatanFungsional(): HasMany
    {
        return $this->hasMany(JabatanFungsionalAkademik::class, 'golongan_pangkat_id');
    }
}
