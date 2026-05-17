<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Plan 削除ユースケース。draft かつ User 参照なしの場合のみ SoftDelete。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw new PlanNotDeletableException;
        }

        if ($plan->users()->exists()) {
            throw new PlanNotDeletableException(
                'このプランは受講者が紐づいているため削除できません。',
            );
        }

        DB::transaction(fn () => $plan->delete());
    }
}
