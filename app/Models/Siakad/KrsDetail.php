<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class KrsDetail extends Model
{
    protected $table = 'siakad_krs_detail';

    protected $fillable = [
        'krs_id',
        'kelas_id',
        'mata_kuliah_id',
        'master_kelas_id',
        'status',
    ];

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'krs_id');
    }

    /**
     * Mata kuliah langsung (baris impor Feeder tanpa kelas, atau salinan dari kelas pada KRS online).
     */
    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    /**
     * Rombel (Master Kelas) peserta; terisi pada baris impor Feeder.
     */
    public function masterKelas()
    {
        return $this->belongsTo(MasterKelas::class, 'master_kelas_id');
    }

    /**
     * Mata kuliah efektif: dari kelas bila ada, jika tidak dari kolom mata_kuliah_id.
     * Eager load 'kelas.mataKuliah' dan 'mataKuliah' untuk menghindari N+1.
     */
    public function getMataKuliahEfektifAttribute(): ?MataKuliah
    {
        return $this->kelas?->mataKuliah ?? $this->mataKuliah;
    }

    public function getMataKuliahEfektifIdAttribute(): ?int
    {
        $id = $this->kelas?->mata_kuliah_id ?? $this->mata_kuliah_id;

        return $id === null ? null : (int) $id;
    }

    /**
     * Filter baris untuk satu mata kuliah, baik lewat kelas maupun kolom mata_kuliah_id.
     */
    public function scopeUntukMataKuliah($query, int $mataKuliahId)
    {
        return $query->where(function ($q) use ($mataKuliahId) {
            $q->where('mata_kuliah_id', $mataKuliahId)
                ->orWhereHas('kelas', fn ($kelas) => $kelas->where('mata_kuliah_id', $mataKuliahId));
        });
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function nilaiMahasiswa()
    {
        return $this->hasOne(NilaiMahasiswa::class, 'krs_detail_id');
    }

    public function nilai()
    {
        return $this->hasOne(NilaiMahasiswa::class, 'krs_detail_id');
    }

    public function nilaiKomponens()
    {
        return $this->hasMany(NilaiKomponenMahasiswa::class, 'krs_detail_id');
    }

    public function ketercapaianCpmks()
    {
        return $this->hasMany(KetercapaianCpmkMahasiswa::class, 'krs_detail_id');
    }

    public function ketercapaianCpmk()
    {
        return $this->hasMany(KetercapaianCpmkMahasiswa::class, 'krs_detail_id');
    }
}
