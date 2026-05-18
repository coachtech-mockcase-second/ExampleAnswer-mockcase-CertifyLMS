<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use Illuminate\Support\Collection;

/**
 * WeaknessAnalysisServiceContract のフォールバック実装。
 *
 * 模試 Feature が未実装、または明示的に弱点判定をオフにしたい環境で利用する。
 * 常に空の Collection を返すため、おすすめバッジは全カテゴリで非表示になる。
 */
final class NullWeaknessAnalysisService implements WeaknessAnalysisServiceContract
{
    /**
     * @return Collection<int, QuestionCategory>
     */
    public function getWeakCategories(Enrollment $enrollment): Collection
    {
        return collect();
    }
}
