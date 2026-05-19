<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API キー
    |--------------------------------------------------------------------------
    |
    | /api/v1/admin/... 配下のエンドポイントを保護する共通 API キー。
    | LMS 運用者が `.env` の ANALYTICS_API_KEY に設定し、GAS の Script Properties
    | に転記して利用する。空文字 / null の場合は ApiKeyMiddleware が 503 を返却する。
    |
    */

    'api_key' => env('ANALYTICS_API_KEY'),

];
