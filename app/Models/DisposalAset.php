<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisposalAset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_disposal_aset';

    protected $fillable = [
        'aset_id',
        'nomor_bap',
        'tanggal_disposal',
        'metode_disposal',
        'nilai_residu',
        'alasan',
        'diajukan_oleh',
        'disetujui_oleh',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_disposal' => 'date',
        'nilai_residu' => 'float',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
