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

    protected $table = 'aset';

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
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'harga_perolehan' => 'decimal:2',
        'nilai_buku' => 'decimal:2',
    ];

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
}
