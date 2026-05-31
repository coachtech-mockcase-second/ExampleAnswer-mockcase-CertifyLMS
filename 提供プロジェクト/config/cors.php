<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Cookie 認証では Access-Control-Allow-Origin: * と Access-Control-Allow-Credentials: true は併存不可。
    // BE-FE 別オリジン構成では具体的なオリジンを設定する。同一オリジン実装の場合も .env で
    // CORS_ALLOWED_ORIGINS を上書きできるようにしておく。
    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8000')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Cookie 認証で必須。fetch(..., { credentials: 'include' }) のリクエストを受け入れる。
    'supports_credentials' => true,

];
