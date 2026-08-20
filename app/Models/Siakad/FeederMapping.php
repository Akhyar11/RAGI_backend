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
        'sync_status',
        'error_message',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
