<?php

namespace App\Models\Sinapra;

use App\Models\Ruangan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterTipeRuangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_master_tipe_ruangan';

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

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'tipe_ruangan_id');
    }
}
