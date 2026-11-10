<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiPengajaranCutoff extends Model
{
    protected $table = 'simpeg_presensi_pengajaran_cutoffs';

    protected $fillable = ['periode', 'cutoff_at', 'set_by'];

    protected $casts = ['cutoff_at' => 'datetime'];

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
