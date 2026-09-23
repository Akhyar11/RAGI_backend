<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboranRuangan extends Model
{
    use HasFactory;

    protected $table = 'sinapra_laboran_ruangan';

    protected $fillable = [
        'ruangan_id',
        'user_id',
        'is_primary',
    ];

    protected $casts = [
        'ruangan_id' => 'integer',
        'user_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Relasi ke Ruangan
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke User (Laboran)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
