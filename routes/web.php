<?php

use App\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| SSO Login Routes (Web - untuk browser redirect flow)
|--------------------------------------------------------------------------
| Halaman login kustom yang dipanggil Passport saat user
| mengakses /oauth/authorize tanpa sesi web aktif.
*/
Route::get('/sso/login', [OAuthController::class, 'showLogin'])
    ->name('sso.login');

Route::post('/sso/login', [OAuthController::class, 'processLogin'])
    ->name('sso.login.process')
    ->middleware('throttle:login');
