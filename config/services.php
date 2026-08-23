<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'scopus' => [
        'api_key' => env('SCOPUS_API_KEY'),
    ],

    'sinta' => [
        'api_token' => env('SINTA_API_TOKEN'),
    ],

    'google_workspace' => [
        'credentials_json' => env('GOOGLE_WORKSPACE_CREDENTIALS'), // Path to JSON file (e.g. storage/app/google-credentials.json)
        'admin_email' => env('GOOGLE_WORKSPACE_ADMIN_EMAIL'),      // Email of the G Suite admin user to impersonate
        'domain' => env('GOOGLE_WORKSPACE_DOMAIN'),
        'default_password' => env('GOOGLE_WORKSPACE_DEFAULT_PASSWORD'),
        'org_unit_path' => env('GOOGLE_WORKSPACE_OU_PATH'),
    ],

];
