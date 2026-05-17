<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan 更新ユースケース。status は本 Action では変更しない(別 Action で状態遷移)。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int, default_meeting_quota: int, sort_order?: ?int} $validated
     */
    public function __invoke(Plan $plan, User $admin, array $validated): Plan
    {
        return DB::transaction(function () use ($plan, $admin, $validated) {
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration_days' => $validated['duration_days'],
                'default_meeting_quota' => $validated['default_meeting_quota'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
