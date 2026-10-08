<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisCpl extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_jenis_cpl';

    protected $fillable = [
        'program_studi_id',
        'kode_jenis',
        'nama_jenis',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
        'program_studi_id' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function cpls()
    {
        return $this->hasMany(Cpl::class, 'jenis_cpl_id');
    }
}
