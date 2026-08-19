<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gedung extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gedung';

    protected $fillable = [
        'kode',
        'nama',
        'jumlah_lantai',
        'alamat',
        'tahun_bangun',
        'luas_m2',
        'status',
    ];

    protected $casts = [
        'jumlah_lantai' => 'integer',
        'tahun_bangun' => 'integer',
        'luas_m2' => 'decimal:2',
    ];

    /**
     * Relasi ke Ruangan
     */
    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'gedung_id');
    }
}
