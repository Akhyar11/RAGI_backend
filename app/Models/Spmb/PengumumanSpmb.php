<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\GelombangPenerimaan;

class PengumumanSpmb extends Model
{
    use HasFactory;

    protected $table = 'pengumuman_spmb';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'gelombang_id',
        'judul',
        'isi',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function gelombangPenerimaan()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }
}
