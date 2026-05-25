<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Plan 削除ユースケース。draft かつ User 参照なしの場合のみ物理削除。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanNotDeletableException::forStatusViolation();
        }

        if ($plan->users()->exists()) {
            throw PlanNotDeletableException::forUsersAttached();
        }

        DB::transaction(fn () => $plan->delete());
    }
}
