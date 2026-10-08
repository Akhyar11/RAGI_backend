<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfesiKarir extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_profesi_karir';

    protected $fillable = [
        'program_studi_id',
        'nama',
        'sumber',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'program_studi_id' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }
}
