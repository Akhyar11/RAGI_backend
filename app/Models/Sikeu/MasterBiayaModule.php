<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBiayaModule extends Model
{
    use HasFactory;

    protected $table = 'core_master_biaya_modules';

    protected $fillable = [
        'master_biaya_id',
        'module_code',
    ];

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }
}
