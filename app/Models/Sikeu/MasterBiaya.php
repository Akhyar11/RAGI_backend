<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBiaya extends Model
{
    use HasFactory;

    protected $table = 'sikeu_master_biaya';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'nominal_standar',
        'deskripsi',
        'is_recurring',
        'is_active',
    ];

    protected $casts = [
        'nominal_standar' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['module_codes'];

    public function modules()
    {
        return $this->hasMany(MasterBiayaModule::class, 'master_biaya_id');
    }

    public function moduleDelegations()
    {
        return $this->hasMany(MasterBiayaModule::class, 'master_biaya_id');
    }

    public function getModuleCodesAttribute()
    {
        $codes = $this->modules->pluck('module_code')->toArray();
        return !empty($codes) ? $codes : ['sikeu'];
    }

    public function detailTagihan()
    {
        return $this->hasMany(DetailTagihan::class, 'master_biaya_id');
    }
}
