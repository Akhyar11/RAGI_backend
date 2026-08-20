<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class FeederSyncLog extends Model
{
    protected $table = 'siakad_feeder_sync_logs';

    protected $fillable = [
        'entity_type',
        'sync_type',
        'total_records',
        'success_count',
        'failed_count',
        'status',
        'details',
        'synced_by',
        'completed_at',
    ];

    protected $casts = [
        'details' => 'array',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }
}
