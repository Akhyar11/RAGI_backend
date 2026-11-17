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
        'source_type',
        'supported_sources',
        'source_schemas',
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

    protected $appends = [
        'token_configured',
    ];

    protected $casts = [
        'expected_schema' => 'array',
        'supported_sources' => 'array',
        'source_schemas' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'token' => 'encrypted',
    ];

    public function getTokenConfiguredAttribute(): bool
    {
        return filled($this->token);
    }

    public function getSupportedSourcesAttribute(): array
    {
        return $this->attributes['supported_sources']
            ? $this->asJsonArray($this->attributes['supported_sources'])
            : ['custom_api'];
    }

    public function getSourceSchemasAttribute(): array
    {
        $schemas = $this->attributes['source_schemas'] ?? null;
        return $schemas ? $this->asJsonArray($schemas) : [];
    }

    private function asJsonArray(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Mengambil nilai token yang telah terdekripsi dengan aman.
     */
    public function getDecryptedToken(): string
    {
        return filled($this->token) ? (string) $this->token : '';
    }
}
