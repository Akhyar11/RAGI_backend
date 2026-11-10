<?php

namespace App\Http\Middleware;

use App\Services\Storage\StorageSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshStorageSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        app(StorageSettingsService::class)->refresh();

        return $next($request);
    }
}
