<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Exceptions\MeetingQuota\MeetingQuotaPlanInvalidTransitionException;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタのアーカイブ解除ユースケース。archived → draft のみ許可。
 * 再販売前提なら再度 publish が必要。誤 archive 時の取り戻し用。
 */
final class UnarchiveAction
{
    /**
     * @throws MeetingQuotaPlanInvalidTransitionException
     */
    public function __invoke(MeetingQuotaPlan $plan, User $admin): MeetingQuotaPlan
    {
        if ($plan->status !== MeetingQuotaPlanStatus::Archived) {
            throw MeetingQuotaPlanInvalidTransitionException::forUnarchive();
        }

        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => MeetingQuotaPlanStatus::Draft->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
