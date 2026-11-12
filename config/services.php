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
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth & API Service
    |--------------------------------------------------------------------------
    | Konfigurasi untuk integrasi Google Drive dan Google Sheets via OAuth 2.0.
    |
    | Autentikasi menggunakan OAuth 2.0 — tidak lagi menggunakan Service Account.
    | Refresh Token disimpan di: storage/app/google/token.json
    | OAuth Client credentials berada di: storage/app/google/oauth-client.json
    |
    | Sesuai dengan: 11_GOOGLE_OAUTH_MIGRATION.md & 12_GOOGLE_API_SERVICE.md
    */
    'google' => [
        'client_id'                  => env('GOOGLE_CLIENT_ID', ''),
        'client_secret'              => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri'               => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
        'drive_root_folder_id'       => env('GOOGLE_DRIVE_ROOT_FOLDER_ID', ''),
        'monitoring_spreadsheet_id'  => env('GOOGLE_SHEET_ID', ''),
        // Path Penyimpanan untuk Render Persistent Disk
        'token_path'                 => env('GOOGLE_TOKEN_PATH', storage_path('app/google/token.json')),
        'settings_path'              => env('GOOGLE_SETTINGS_PATH', storage_path('app/google/settings.json')),
        'client_path'                => env('GOOGLE_CLIENT_PATH', storage_path('app/google/oauth-client.json')),
    ],

];
