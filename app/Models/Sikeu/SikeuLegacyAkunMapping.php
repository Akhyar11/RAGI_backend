<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SikeuLegacyAkunMapping extends Model
{
    use HasFactory;

    protected $table = 'sikeu_mig_akun_mapping';

    protected $fillable = [
        'kd_perkiraan_lama',
        'nama_akun_lama',
        'akun_id_baru',
        'kode_akun_baru',
    ];

    public function akunBaru()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_id_baru');
    }
}
