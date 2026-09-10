<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class SettingTarif extends Model
{
    protected $table = 'sikeu_setting_tarif';

    protected $fillable = [
        'master_biaya_id',
        'tahun_angkatan',
        'program_studi_id',
        'semester',
        'jalur_kelas',
        'nominal',
        'is_active',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'float',
        'is_active' => 'boolean',
        'tahun_angkatan' => 'integer',
        'semester' => 'integer',
    ];

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }
}
