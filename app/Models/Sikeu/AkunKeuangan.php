<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AkunKeuangan extends Model
{
    use HasFactory;

    protected $table = 'akun_keuangan';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'kelompok',
        'saldo_normal',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function detailJurnal()
    {
        return $this->hasMany(DetailJurnalUmum::class, 'akun_id');
    }
}
