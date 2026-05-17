<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Enums\ContentStatus;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 指定 Section 配下の SectionQuestion 一覧を取得するユースケース。
 * category_id / status のフィルタを受け取り、ordered + paginate(20) で返却する。
 */
final class IndexAction
{
    public function __invoke(
        Section $section,
        ?string $categoryId,
        ?ContentStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $section->questions()
            ->with(['category', 'options' => fn ($q) => $q->ordered()])
            ->byCategory($categoryId)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
