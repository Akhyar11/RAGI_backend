<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisBiaya extends Model
{
    use HasFactory;

    protected $table = 'jenis_biaya';

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

    public function moduleDelegations()
    {
        return $this->hasMany(JenisBiayaModule::class, 'jenis_biaya_id');
    }

    public function getModuleCodesAttribute()
    {
        $codes = $this->moduleDelegations->pluck('module_code')->toArray();
        return !empty($codes) ? $codes : ['sikeu'];
    }

    public function tarifUkt()
    {
        return $this->hasMany(TarifUkt::class, 'jenis_biaya_id');
    }

    public function detailTagihan()
    {
        return $this->hasMany(DetailTagihan::class, 'jenis_biaya_id');
    }
}
