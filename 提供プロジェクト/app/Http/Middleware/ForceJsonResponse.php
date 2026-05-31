<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/api/*` 配下のリクエストの `Accept` ヘッダを `application/json` に固定し、
 * Laravel 標準の `ValidationException` / `HttpException` レンダリングを HTML redirect ではなく
 * JSON レスポンスにそろえる。
 *
 * 運用エクスポート API は curl / GAS UrlFetchApp / Apps Script 等の様々なクライアントから叩かれ、
 * `Accept` ヘッダを明示しないケースもあるため Middleware で明示的に固定する。
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
