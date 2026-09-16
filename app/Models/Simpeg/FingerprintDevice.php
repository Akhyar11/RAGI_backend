<?php

namespace App\Models\Simpeg;

use App\Models\OfficeLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDevice extends Model
{
    use HasFactory;

    protected $table = 'simpeg_fingerprint_devices';

    protected $fillable = [
        'device_name',
        'device_code',
        'ip_address',
        'port',
        'location',
        'office_location_id',
        'device_model',
        'is_active',
        'last_sync_at',
        'last_status',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class, 'office_location_id');
    }
}
