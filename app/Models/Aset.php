<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_aset';

    protected $fillable = [
        'kategori_id',
        'ruangan_id',
        'kode_aset',
        'nama',
        'merk',
        'model',
        'serial_number',
        'tanggal_perolehan',
        'harga_perolehan',
        'nilai_buku',
        'kondisi',
        'status',
        'is_borrowable',
        'is_lab_asset',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'harga_perolehan' => 'decimal:2',
        'nilai_buku' => 'decimal:2',
        'is_borrowable' => 'boolean',
        'is_lab_asset' => 'boolean',
    ];

    /**
     * Scope untuk aset yang dapat dipinjam
     */
    public function scopeBorrowable($query)
    {
        return $query->where('is_borrowable', true);
    }

    /**
     * Scope untuk membatasi aset hanya pada lab binaan laboran
     */
    public function scopeForLaboran($query, User $user)
    {
        $ruanganIds = $user->laboranRuangan()->pluck('sinapra_ruangan.id');
        return $query->whereIn('ruangan_id', $ruanganIds);
    }

    /**
     * Relasi ke Kategori Aset
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_id');
    }

    /**
     * Relasi ke Ruangan (Lokasi Aset)
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * Relasi ke Peminjaman Aset
     */
    public function peminjaman(): HasMany
    {
        return $this->hasMany(PeminjamanAset::class, 'aset_id');
    }

    /**
     * Relasi ke Maintenance Log
     */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'aset_id');
    }

    /**
     * Relasi ke Mutasi Aset
     */
    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiAset::class, 'aset_id');
    }

    /**
     * Relasi ke Disposal Aset
     */
    public function disposal(): HasMany
    {
        return $this->hasMany(DisposalAset::class, 'aset_id');
    }
}

