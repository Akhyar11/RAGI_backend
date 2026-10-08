<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RumpunMataKuliah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_rumpun_mk';

    protected $fillable = [
        'program_studi_id',
        'kode_rumpun',
        'nama_rumpun',
        'dosen_koordinator_id',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'program_studi_id' => 'integer',
        'dosen_koordinator_id' => 'integer',
    ];

    public function dosenKoordinator()
    {
        return $this->belongsTo(Dosen::class, 'dosen_koordinator_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function mataKuliahs()
    {
        return $this->hasMany(MataKuliah::class, 'rumpun_mk_id');
    }
}
