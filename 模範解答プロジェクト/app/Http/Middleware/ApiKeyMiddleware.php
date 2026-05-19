<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\MockExamSessionController;
use App\Http\Controllers\Api\UserController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1/admin/... 配下を保護する共通 API キー Middleware。
 *
 * `X-API-KEY` ヘッダを `config('analytics-export.api_key')` と `hash_equals` で比較する。
 * 不一致 / 欠落 → 401、API キー未設定 (config が空) → 503 を JSON で返却する。
 *
 * 認可はキー単一の運用前提とし、ユーザー単位ロール認可は本 Middleware では実装しない。
 *
 * @see UserController
 * @see EnrollmentController
 * @see MockExamSessionController
 */
final class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('analytics-export.api_key');

        if (! is_string($configured) || $configured === '') {
            return $this->jsonError(
                message: 'API キー未設定',
                errorCode: 'API_KEY_NOT_CONFIGURED',
                status: 503,
            );
        }

        $provided = (string) $request->header('X-API-KEY', '');

        if ($provided === '' || ! hash_equals($configured, $provided)) {
            return $this->jsonError(
                message: 'API キーが無効です。',
                errorCode: 'INVALID_API_KEY',
                status: 401,
            );
        }

        return $next($request);
    }

    private function jsonError(string $message, string $errorCode, int $status): JsonResponse
    {
        return response()
            ->json([
                'message' => $message,
                'error_code' => $errorCode,
                'status' => $status,
            ], $status)
            ->header('Content-Type', 'application/json; charset=UTF-8');
    }
}
