<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の資格マスタ一覧をフィルタ付きで取得するユースケース。
 * 公開中 → 下書き → アーカイブ の順で並び、同 status 内は最終更新の降順。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?CertificationStatus $status,
        ?string $categoryId,
        ?CertificationDifficulty $difficulty,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Certification::query()
            ->with('category')
            ->withCount(['coaches', 'certificates']);

        $query->keyword($keyword);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($difficulty !== null) {
            $query->where('difficulty', $difficulty->value);
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(status, 'published', 'draft', 'archived')");
        } else {
            // SQLite では FIELD() が使えないため CASE 式で同等の優先順位を表現する
            $query->orderByRaw(
                "CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END"
            );
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
