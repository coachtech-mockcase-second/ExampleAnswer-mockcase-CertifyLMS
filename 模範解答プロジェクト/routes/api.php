<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Wave 0b 骨組み: Sanctum 認証 group の雛形のみ。
| 各 Feature が実装フェーズで自身の API ルートを追加していく。
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
