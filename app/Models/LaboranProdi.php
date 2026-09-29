<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboranProdi extends Model
{
    use HasFactory;

    protected $table = 'sinapra_laboran_prodi';

    protected $fillable = [
        'user_id',
        'program_studi_id',
        'is_primary',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'program_studi_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Relasi ke Program Studi (SIAKAD)
     */
    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siakad\ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Relasi ke User (Laboran)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
