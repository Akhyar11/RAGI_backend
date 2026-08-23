<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisBiayaModule extends Model
{
    use HasFactory;

    protected $table = 'core_jenis_biaya_modules';

    protected $fillable = [
        'jenis_biaya_id',
        'module_code',
    ];

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }
}
