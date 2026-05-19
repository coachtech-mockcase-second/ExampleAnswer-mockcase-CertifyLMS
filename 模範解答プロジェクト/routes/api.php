<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\MockExamSessionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Web セッション認証 / Sanctum / Fortify は使わず、X-API-KEY ヘッダで保護する読み取り専用
| エクスポート API のみを提供する。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// 運用エクスポート API (X-API-KEY 認証、レート制限 60req/min)
// ============================================================
Route::prefix('v1/admin')
    ->middleware(['api.key', 'throttle:60,1'])
    ->name('api.v1.admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('mock-exam-sessions', [MockExamSessionController::class, 'index'])->name('mock-exam-sessions.index');
    });
