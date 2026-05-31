<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\PassProbabilityBand;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use Illuminate\Support\Collection;

/**
 * 苦手分野ドリル UI / 受講生ダッシュボードが呼び出す弱点分析の契約。
 *
 * 正規実装は模試 Feature 側が提供する。Container 未バインド時のフォールバックとして
 * NullWeaknessAnalysisService を QuizAnsweringServiceProvider が登録する。
 */
interface WeaknessAnalysisServiceContract
{
    /**
     * 当該 Enrollment における「苦手」と判定された QuestionCategory のコレクションを返す。
     *
     * @return Collection<int, QuestionCategory>
     */
    public function getWeakCategories(Enrollment $enrollment): Collection;

    /**
     * 当該 Enrollment の合格可能性バンド(safe / warning / danger / unknown)を返す。
     * 採点済セッションが 0 件の場合は Unknown を返す。
     */
    public function getPassProbabilityBand(Enrollment $enrollment): PassProbabilityBand;
}
