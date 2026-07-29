<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'client_app',
    'client_name',
    'passport_client_id',
    'allowed_redirect_uris',
    'is_active',
])]
class OauthAppClient extends Model
{
    protected function casts(): array
    {
        return [
            'allowed_redirect_uris' => 'array',
            'is_active'             => 'boolean',
        ];
    }

    /**
     * Cek apakah redirect_uri yang diminta ada dalam whitelist.
     */
    public function isRedirectUriAllowed(string $uri): bool
    {
        return in_array($uri, $this->allowed_redirect_uris ?? []);
    }

    /**
     * Ambil client aktif berdasarkan client_app slug.
     */
    public static function findActive(string $clientApp): ?self
    {
        return static::where('client_app', $clientApp)
            ->where('is_active', true)
            ->first();
    }
}
