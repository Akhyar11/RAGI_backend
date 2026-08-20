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

/*
|--------------------------------------------------------------------------
| Public Storage Route Fallback
|--------------------------------------------------------------------------
| Memastikan file dokumen pendaftaran yang diunggah ke storage/app/public
| selalu dapat diakses secara publik tanpa 403 Forbidden.
*/
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404);
    }
    return response()->file($filePath);
})->where('path', '.*');
