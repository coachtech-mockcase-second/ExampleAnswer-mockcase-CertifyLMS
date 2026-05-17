<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Models\MeetingQuotaPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 追加面談 SKU マスタ一覧取得ユースケース。status / keyword フィルタを適用し、
 * status(published 優先) → sort_order ASC → created_at DESC で並べる。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?MeetingQuotaPlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MeetingQuotaPlan::query()->withCount('payments');

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', "%{$keyword}%");
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(status, 'published', 'draft', 'archived')");
        } else {
            $query->orderByRaw(
                "CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END"
            );
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
