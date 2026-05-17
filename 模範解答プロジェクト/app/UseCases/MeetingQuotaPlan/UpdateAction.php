<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタ更新ユースケース。status は本 Action では変更しない(別 Action で状態遷移)。
 */
final class UpdateAction
{
    /**
     * @param  array{name: string, description?: ?string, meeting_count: int, price: int, stripe_price_id?: ?string, sort_order?: ?int}  $validated
     */
    public function __invoke(MeetingQuotaPlan $plan, User $admin, array $validated): MeetingQuotaPlan
    {
        return DB::transaction(function () use ($plan, $admin, $validated) {
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'meeting_count' => $validated['meeting_count'],
                'price' => $validated['price'],
                'stripe_price_id' => $validated['stripe_price_id'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
