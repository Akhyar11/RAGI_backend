<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanSks extends Model
{
    use HasFactory;

    protected $table = 'siakad_aturan_sks';

    protected $fillable = [
        'program_studi_id',
        'ipk_min',
        'ipk_max',
        'max_sks',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'ipk_min' => 'float',
        'ipk_max' => 'float',
        'max_sks' => 'int',
        'is_active' => 'boolean',
    ];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }
}
