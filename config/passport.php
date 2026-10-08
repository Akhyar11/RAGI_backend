<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Web Session Access Token TTL
    |--------------------------------------------------------------------------
    |
    | Durasi (menit) access token Passport yang diterbitkan khusus untuk
    | sesi web interaktif (AuthController::login/register/mfaLoginVerify).
    | Ditegakkan per-baris oauth_access_tokens oleh middleware
    | EnsurePassportTokenIsFresh. Token mobile (API\AuthController) dan
    | alur OAuth lain tetap memakai TTL global personalAccessTokensExpireIn.
    |
    */

    'web_access_token_minutes' => (int) env('PASSPORT_WEB_ACCESS_TOKEN_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Personal Access Token Default TTL
    |--------------------------------------------------------------------------
    |
    | Default global (hari) untuk personal access token Passport. Dipakai
    | khusus kompatibilitas token mobile/integrasi tanpa alur refresh.
    |
    */

    'personal_access_expire_days' => (int) env('PASSPORT_PAT_EXPIRE_DAYS', 365),

];
