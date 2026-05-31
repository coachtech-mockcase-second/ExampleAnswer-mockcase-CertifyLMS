<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PassProbabilityBand;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use Illuminate\Support\Collection;

/**
 * WeaknessAnalysisServiceContract のフォールバック実装。
 *
 * 模試 Feature が未実装、または明示的に弱点判定をオフにしたい環境で利用する。
 * 弱点カテゴリは空 Collection、合格可能性は判定不可(Unknown)を返す。
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

    public function getPassProbabilityBand(Enrollment $enrollment): PassProbabilityBand
    {
        return PassProbabilityBand::Unknown;
    }
}
