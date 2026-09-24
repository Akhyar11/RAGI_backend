<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_biaya';

    protected $fillable = [
        'gelombang_id',
        'program_studi_id',
        'total_biaya',
        'is_active',
        'keterangan',
    ];

    protected $casts = [
        'total_biaya' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(MasterBiayaItem::class, 'master_biaya_id');
    }

    public function gelombang()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('nominal');
        $this->update(['total_biaya' => $total]);
    }
}
