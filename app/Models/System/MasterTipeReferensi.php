<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterTipeReferensi extends Model
{
    protected $table = 'core_tipe_referensi';

    protected $fillable = [
        'kode',
        'nama',
        'modul',
        'deskripsi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    /**
     * Relasi ke data item referensi berdasarkan kolom `tipe` dan `kode`
     */
    public function items(): HasMany
    {
        return $this->hasMany(MasterReferensi::class, 'tipe', 'kode');
    }

    /**
     * Scope untuk tipe referensi aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk memfilter modul (default menyertakan global dan modul bersangkutan)
     */
    public function scopeForModule($query, ?string $module = null)
    {
        if (empty($module) || $module === 'all') {
            return $query;
        }

        return $query->where(function ($q) use ($module) {
            $q->where('modul', $module)
              ->orWhere('modul', 'global');
        });
    }
}
