<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPengadaan extends Model
{
    use HasFactory;

    protected $table = 'detail_pengadaan';

    protected $fillable = [
        'pengajuan_id',
        'kategori_aset_id',
        'nama_barang',
        'spesifikasi',
        'jumlah',
        'satuan',
        'harga_satuan_estimasi',
        'total_estimasi',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'harga_satuan_estimasi' => 'decimal:2',
        'total_estimasi' => 'decimal:2',
    ];

    /**
     * Relasi ke Header Pengajuan Pengadaan
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPengadaan::class, 'pengajuan_id');
    }

    /**
     * Relasi ke Kategori Aset
     */
    public function kategoriAset(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_aset_id');
    }
}
