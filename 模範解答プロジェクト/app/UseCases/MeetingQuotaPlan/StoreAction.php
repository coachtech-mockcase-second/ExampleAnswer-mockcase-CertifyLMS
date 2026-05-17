<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタ新規作成ユースケース。status=draft 固定で INSERT。
 */
final class StoreAction
{
    /**
     * @param  array{name: string, description?: ?string, meeting_count: int, price: int, stripe_price_id?: ?string, sort_order?: ?int}  $validated
     */
    public function __invoke(User $admin, array $validated): MeetingQuotaPlan
    {
        return DB::transaction(fn () => MeetingQuotaPlan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => MeetingQuotaPlanStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]));
    }
}
