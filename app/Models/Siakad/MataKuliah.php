<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MataKuliah extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_mata_kuliah';

    protected $fillable = [
        'kurikulum_id',
        'rumpun_mk_id',
        'kode_mk',
        'nama',
        'kategori',
        'sks_teori',
        'sks_praktik',
        'total_sks',
        'semester_anjuran',
        'jumlah_pertemuan',
        'tipe',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kurikulum_id' => 'integer',
        'rumpun_mk_id' => 'integer',
        'sks_teori' => 'integer',
        'sks_praktik' => 'integer',
        'total_sks' => 'integer',
        'semester_anjuran' => 'integer',
        'jumlah_pertemuan' => 'integer',
    ];

    public function rumpunMataKuliah()
    {
        return $this->belongsTo(RumpunMataKuliah::class, 'rumpun_mk_id');
    }

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'mata_kuliah_id');
    }

    public function prasyarats()
    {
        return $this->hasMany(PrasyaratMk::class, 'mata_kuliah_id');
    }

    public function cpmks()
    {
        return $this->hasMany(Cpmk::class, 'mata_kuliah_id');
    }

    public function bahanKajians()
    {
        return $this->belongsToMany(BahanKajian::class, 'siakad_mata_kuliah_bahan_kajian', 'mata_kuliah_id', 'bahan_kajian_id');
    }

    public function cpls()
    {
        return $this->belongsToMany(Cpl::class, 'siakad_mata_kuliah_cpl', 'mata_kuliah_id', 'cpl_id');
    }

    /**
     * Rumusan CPMK Program Studi (CPMK-PS) yang dibebankan ke mata kuliah ini.
     *
     * Berbeda dengan relasi {@see cpmks()} (CPMK per mata kuliah), sumber
     * CPL/CPMK yang aktif dipakai modul OBE adalah CPMK-PS melalui pivot
     * `siakad_cpmk_prodi_mata_kuliah`.
     */
    public function cpmkProdis()
    {
        return $this->belongsToMany(
            CpmkProdi::class,
            'siakad_cpmk_prodi_mata_kuliah',
            'mata_kuliah_id',
            'cpmk_prodi_id'
        )->withTimestamps();
    }
}
