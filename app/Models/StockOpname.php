<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_stock_opname';

    protected $fillable = [
        'ruangan_id',
        'kode_opname',
        'tanggal_mulai',
        'tanggal_selesai',
        'petugas_user_id',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }
}
