<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TarifUktSpmb extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_tarif_ukt';

    protected $fillable = [
        'nama',
        'deskripsi',
        'master_sikeu_biaya_id',
        'master_program_studi_id',
    ];

    protected $casts = [
        'deskripsi' => 'string',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'master_program_studi_id');
    }

    public function masterSikeuBiaya()
    {
        return $this->belongsTo(\App\Models\Sikeu\MasterBiaya::class, 'master_sikeu_biaya_id');
    }
}