<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標の編集 Action。title / description / target_date のみ更新可。
 * achieved_at は専用 Action(MarkAchievedAction / UnmarkAchievedAction) 経由のみで更新する。
 */
final class UpdateAction
{
    /**
     * @param  array{title: string, description?: ?string, target_date?: ?string}  $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        $goal->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
        ]);

        return $goal->refresh();
    }
}
