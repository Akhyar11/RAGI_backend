<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterKategoriSkp extends Model
{
    use HasFactory;

    protected $table = 'simpeg_master_kategori_skp';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SkpItem::class, 'kategori_skp_id');
    }
}
