<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Requests\Api\Enrollment\IndexRequest;
use App\Http\Resources\Api\EnrollmentResource;
use App\UseCases\AnalyticsExport\Enrollment\IndexAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 運用エクスポート API: 受講登録一覧。
 *
 * フィルタ / Eager Loading 解釈 / バッチ集計呼出は IndexAction に委譲する。
 * 認可は ApiKeyMiddleware で完結。
 *
 * @see ApiKeyMiddleware
 * @see IndexAction
 */
final class EnrollmentController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        return EnrollmentResource::collection(
            $action($request->validated(), $this->mapIncludes($request->resolveIncludes())),
        );
    }

    /**
     * `?include=user,certification` をリレーション名 (camelCase) 配列に変換する。
     *
     * @param  array<int, string>  $resolved
     * @return array<int, string>
     */
    private function mapIncludes(array $resolved): array
    {
        $map = [
            'user' => 'user',
            'certification' => 'certification',
        ];

        return array_values(array_intersect_key($map, array_flip($resolved)));
    }
}
