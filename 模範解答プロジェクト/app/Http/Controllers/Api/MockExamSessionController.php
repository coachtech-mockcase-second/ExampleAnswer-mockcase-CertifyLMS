<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Requests\Api\MockExamSession\IndexRequest;
use App\Http\Resources\Api\MockExamSessionResource;
use App\UseCases\AnalyticsExport\MockExamSession\IndexAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 運用エクスポート API: 模試セッション一覧。
 *
 * フィルタ / Eager Loading 解釈 / バッチ集計呼出は IndexAction に委譲する。
 * 認可は ApiKeyMiddleware で完結。
 *
 * @see ApiKeyMiddleware
 * @see IndexAction
 */
final class MockExamSessionController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        return MockExamSessionResource::collection(
            $action($request->validated(), $this->mapIncludes($request->resolveIncludes())),
        );
    }

    /**
     * `?include=user,mock_exam,enrollment` を Eloquent リレーション名 (camelCase) に変換する。
     *
     * @param array<int, string> $resolved
     *
     * @return array<int, string>
     */
    private function mapIncludes(array $resolved): array
    {
        $map = [
            'user' => 'user',
            'mock_exam' => 'mockExam',
            'enrollment' => 'enrollment',
        ];

        return array_values(array_intersect_key($map, array_flip($resolved)));
    }
}
