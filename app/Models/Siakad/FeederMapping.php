<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class FeederMapping extends Model
{
    protected $table = 'siakad_feeder_mappings';

    protected $fillable = [
        'entity_type',
        'local_id',
        'feeder_id',
        'raw_data',
        'sync_status',
        'error_message',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
