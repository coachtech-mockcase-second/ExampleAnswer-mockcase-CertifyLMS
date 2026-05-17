<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 受講中(in_progress)以外のユーザー(graduated / suspended / 招待中等)を 403 で弾く Middleware。
 * 受講中前提のリソース(追加面談購入 / 面談予約 / mock-exam 受験等)のルートグループで利用する。
 */
final class EnsureActiveLearning
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== UserStatus::InProgress) {
            abort(403, '受講中のユーザーのみアクセスできます。');
        }

        return $next($request);
    }
}
