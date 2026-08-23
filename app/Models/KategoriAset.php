<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriAset extends Model
{
    use HasFactory;

    protected $table = 'sinapra_kategori_aset';

    protected $fillable = [
        'induk_id',
        'kode',
        'nama',
        'masa_manfaat_tahun',
        'tarif_penyusutan_persen',
    ];

    protected $casts = [
        'masa_manfaat_tahun' => 'integer',
        'tarif_penyusutan_persen' => 'decimal:2',
    ];

    /**
     * Relasi ke Induk Kategori (Self-referencing)
     */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'induk_id');
    }

    /**
     * Relasi ke Sub Kategori (Self-referencing)
     */
    public function subKategori(): HasMany
    {
        return $this->hasMany(KategoriAset::class, 'induk_id');
    }

    /**
     * Relasi ke Aset
     */
    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'kategori_id');
    }
}
