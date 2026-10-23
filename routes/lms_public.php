<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Siakad\LmsController;

/*
|--------------------------------------------------------------------------
| LMS Module — Public API Routes
|--------------------------------------------------------------------------
| Prefix: /api/v1/lms (tanpa middleware auth:api)
|
| Hanya berisi endpoint non-sensitif yang dibutuhkan UI sebelum login
| (batas upload, feature flag). Didaftarkan terpisah dari routes/lms.php
| karena seluruh isi file tersebut berada di grup `auth:api`.
*/

Route::get('/public/settings', [LmsController::class, 'publicSettings']);
