<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_ruangan';

    protected $fillable = [
        'gedung_id',
        'tipe_ruangan_id',
        'program_studi_id',
        'kode',
        'nama',
        'lantai',
        'tipe',
        'kapasitas',
        'ada_ac',
        'jumlah_ac',
        'ada_proyektor',
        'jumlah_proyektor',
        'ada_wifi',
        'jumlah_wifi',
        'status',
    ];

    protected $casts = [
        'tipe_ruangan_id' => 'integer',
        'program_studi_id' => 'integer',
        'lantai' => 'integer',
        'kapasitas' => 'integer',
        'ada_ac' => 'boolean',
        'jumlah_ac' => 'integer',
        'ada_proyektor' => 'boolean',
        'jumlah_proyektor' => 'integer',
        'ada_wifi' => 'boolean',
        'jumlah_wifi' => 'integer',
    ];

    /**
     * Relasi ke Program Studi (SIAKAD)
     */
    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siakad\ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Relasi ke Master Tipe Ruangan
     */
    public function tipeRuangan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sinapra\MasterTipeRuangan::class, 'tipe_ruangan_id');
    }

    /**
     * Relasi ke Gedung
     */
    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class, 'gedung_id');
    }

    /**
     * Relasi ke Aset
     */
    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'ruangan_id');
    }

    /**
     * Relasi ke Peminjaman Ruangan
     */
    public function peminjaman(): HasMany
    {
        return $this->hasMany(PeminjamanRuangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke Maintenance Log
     */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'ruangan_id');
    }

    /**
     * Relasi ke penugasan laboran
     */
    public function laboranRuangan(): HasMany
    {
        return $this->hasMany(LaboranRuangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke User laboran yang ditugaskan
     */
    public function laboran()
    {
        return $this->belongsToMany(User::class, 'sinapra_laboran_ruangan', 'ruangan_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Scope ruangan bertipe laboratorium
     */
    public function scopeIsLab($query)
    {
        return $query->where('tipe', 'lab');
    }

    /**
     * Scope untuk membatasi ruangan yang dapat diakses oleh laboran sesuai prodi binaan atau penugasan langsung
     */
    public function scopeForLaboran($query, User $user)
    {
        $prodiIds = $user->getSinapraProdiIds();
        $accessibleRuanganIds = $user->getSinapraAccessibleRuanganIds();

        return $query->where(function ($q) use ($prodiIds, $accessibleRuanganIds, $user) {
            $hasCondition = false;
            if ($prodiIds->isNotEmpty()) {
                $q->whereIn('program_studi_id', $prodiIds);
                $hasCondition = true;
            }
            if ($accessibleRuanganIds->isNotEmpty()) {
                if ($hasCondition) {
                    $q->orWhereIn('id', $accessibleRuanganIds);
                } else {
                    $q->whereIn('id', $accessibleRuanganIds);
                }
                $hasCondition = true;
            }
            if (!$hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
