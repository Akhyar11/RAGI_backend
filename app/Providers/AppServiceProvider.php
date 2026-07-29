<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Arahkan Passport ke halaman login SSO kustom kita
        // saat user mengakses /oauth/authorize tanpa sesi web aktif
        Passport::authorizationView(fn ($params) =>
            redirect('/sso/login?' . http_build_query($params))
        );

        // Token access Passport berlaku 1 hari
        Passport::tokensExpireIn(now()->addDay());

        // Refresh token berlaku 30 hari
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // Personal access token berlaku 1 tahun
        Passport::personalAccessTokensExpireIn(now()->addYear());
    }
}
