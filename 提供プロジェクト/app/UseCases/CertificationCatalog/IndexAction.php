<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCatalog;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * 受講生向け資格カタログの一覧を取得するユースケース。
 *
 * 戻り値:
 * - `catalog`: 公開中の全資格（カテゴリ / 難易度フィルタ適用後）
 * - `enrolled_ids`: 受講登録済資格 ID（カード上の「受講中」バッジ判定用）
 */
final class IndexAction
{
    /**
     * @param array{category_id?: string|null, difficulty?: string|null} $filter
     *
     * @return array{catalog: Collection<int, Certification>, enrolled_ids: SupportCollection<int, string>}
     */
    public function __invoke(User $student, array $filter): array
    {
        $catalog = Certification::query()
            ->published()
            ->with(['category', 'coaches'])
            ->when(
                $filter['category_id'] ?? null,
                fn ($q, string $id) => $q->where('category_id', $id),
            )
            ->when(
                $filter['difficulty'] ?? null,
                fn ($q, string $d) => $q->where('difficulty', $d),
            )
            ->orderBy('name')
            ->get();

        $enrolledIds = $student->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
                EnrollmentStatus::Failed->value,
            ])
            ->pluck('certification_id');

        return [
            'catalog' => $catalog,
            'enrolled_ids' => $enrolledIds,
        ];
    }
}
