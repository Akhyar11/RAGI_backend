<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;

class Fakultas extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_fakultas';

    protected $fillable = [
        'kode',
        'nama',
        'nama_singkat',
        'dekan_id',
        'telepon',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programStudis()
    {
        return $this->hasMany(MasterProgramStudi::class, 'fakultas_id');
    }
}
