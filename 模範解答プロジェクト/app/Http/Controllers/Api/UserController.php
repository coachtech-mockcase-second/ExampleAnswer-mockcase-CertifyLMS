<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Requests\Api\User\IndexRequest;
use App\Http\Resources\Api\UserResource;
use App\UseCases\AnalyticsExport\User\IndexAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 運用エクスポート API: ユーザー一覧。
 *
 * 並び順 / フィルタ / `withdrawn` 除外などの取得ロジックは IndexAction に委譲する。
 * 認可は ApiKeyMiddleware で完結 (ユーザー単位ロール認可は持たない)。
 *
 * @see ApiKeyMiddleware
 * @see IndexAction
 */
final class UserController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        return UserResource::collection($action($request->validated()));
    }
}
