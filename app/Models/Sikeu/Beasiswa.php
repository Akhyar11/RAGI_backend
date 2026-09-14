<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;

class Beasiswa extends Model
{
    protected $table = 'sikeu_beasiswa';

    protected $fillable = [
        'kode',
        'nama',
        'sumber',
        'tipe_potongan',
        'nilai_potongan',
        'berlaku_angkatan_mulai',
        'berlaku_angkatan_sampai',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'nilai_potongan' => 'float',
        'is_active' => 'boolean',
        'berlaku_angkatan_mulai' => 'integer',
        'berlaku_angkatan_sampai' => 'integer',
    ];

    protected $appends = ['jenis_biaya_ids'];

    /**
     * Komponen biaya (sikeu_master_biaya) yang dicakup beasiswa.
     * Kosong ([]) berarti berlaku global untuk semua komponen biaya.
     */
    public function jenisBiaya()
    {
        return $this->belongsToMany(MasterBiaya::class, 'sikeu_beasiswa_jenis_biaya', 'beasiswa_id', 'jenis_biaya_id');
    }

    public function getJenisBiayaIdsAttribute()
    {
        return $this->jenisBiaya()->pluck('sikeu_master_biaya.id')->map(fn ($id) => (int) $id)->values()->all();
    }
}