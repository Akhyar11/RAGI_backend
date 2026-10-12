<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IamApiIntegration extends Model
{
    use SoftDeletes;

    protected $table = 'iam_api_integrations';

    protected $fillable = [
        'key',
        'name',
        'module',
        'http_method',
        'description',
        'expected_schema',
        'url_api',
        'token',
        'auth_type',
        'is_active',
        'last_synced_at',
        'last_status',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'expected_schema' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'token' => 'encrypted',
    ];
}
