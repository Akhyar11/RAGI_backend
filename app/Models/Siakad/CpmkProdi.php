<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rumusan CPMK Program Studi (CPMK-PS).
 *
 * Berbeda dengan {@see Cpmk} yang merupakan CPMK per Mata Kuliah, model ini
 * mewakili rumusan CPMK pada level program studi: terikat kurikulum dan satu CPL
 * tanpa terikat mata kuliah.
 */
class CpmkProdi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_cpmk_prodi';

    protected $fillable = [
        'kurikulum_id',
        'cpl_id',
        'kode_cpmk',
        'deskripsi',
    ];

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function cpl()
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }

    public function mataKuliahs()
    {
        return $this->belongsToMany(
            MataKuliah::class,
            'siakad_cpmk_prodi_mata_kuliah',
            'cpmk_prodi_id',
            'mata_kuliah_id'
        )->withTimestamps();
    }

    /**
     * Program studi pemilik, diturunkan dari kurikulum sehingga tidak perlu
     * kolom `program_studi_id` tersendiri (konsisten dengan aturan OBE admin).
     */
    public function getProgramStudiIdAttribute(): ?int
    {
        return $this->kurikulum?->program_studi_id !== null
            ? (int) $this->kurikulum->program_studi_id
            : null;
    }
}