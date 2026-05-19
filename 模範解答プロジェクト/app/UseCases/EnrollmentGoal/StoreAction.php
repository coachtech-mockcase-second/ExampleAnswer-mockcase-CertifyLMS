<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

/**
 * 受講生本人が Enrollment 配下に個人目標を追加する Action。
 * achieved_at は初期 null(未達成)。認可は Controller 側の Policy で完結。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: ?string, target_date?: ?string} $validated
     */
    public function __invoke(Enrollment $enrollment, array $validated): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
            'achieved_at' => null,
        ]);
    }
}
