<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Exceptions\MeetingQuota\MeetingQuotaPlanInvalidTransitionException;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタ公開ユースケース。draft → published のみ許可。
 */
final class PublishAction
{
    /**
     * @throws MeetingQuotaPlanInvalidTransitionException
     */
    public function __invoke(MeetingQuotaPlan $plan, User $admin): MeetingQuotaPlan
    {
        if ($plan->status !== MeetingQuotaPlanStatus::Draft) {
            throw MeetingQuotaPlanInvalidTransitionException::forPublish();
        }

        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => MeetingQuotaPlanStatus::Published->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
