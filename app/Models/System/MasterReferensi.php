<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterReferensi extends Model
{
    use HasFactory;

    protected $table = 'spmb_master_referensi';

    protected $fillable = [
        'tipe',
        'modul',
        'kode',
        'nama',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    /**
     * Scope a query to only include active references.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to include references for a specific module or global.
     */
    public function scopeForModule($query, $module)
    {
        if (empty($module) || $module === 'all') {
            return $query;
        }

        return $query->where(function ($q) use ($module) {
            $q->where('modul', $module)
              ->orWhere('modul', 'global');
        });
    }

    /**
     * Relasi ke Master Tipe Referensi
     */
    public function tipeRef()
    {
        return $this->belongsTo(MasterTipeReferensi::class, 'tipe', 'kode');
    }
}
