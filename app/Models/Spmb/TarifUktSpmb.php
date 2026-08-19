<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TarifUktSpmb extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tarif_ukt_spmb';

    protected $fillable = [
        'program_studi_id',
        'tahun_akademik_id',
        'kelompok_ukt',
        'nominal',
        'is_active',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(MasterTahunAkademik::class, 'tahun_akademik_id');
    }
}
