<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterKategoriKebijakan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_kategori_kebijakan';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function kebijakanPedoman(): HasMany
    {
        return $this->hasMany(KebijakanPedoman::class, 'kategori_kebijakan_id');
    }
}
