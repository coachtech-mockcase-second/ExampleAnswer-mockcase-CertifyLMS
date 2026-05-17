<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Exceptions\MeetingQuota\MeetingQuotaPlanInvalidTransitionException;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタアーカイブユースケース。published → archived のみ許可。
 * アーカイブ後は受講生の購入画面に表示されなくなる。過去の Payment 履歴は残す。
 */
final class ArchiveAction
{
    /**
     * @throws MeetingQuotaPlanInvalidTransitionException
     */
    public function __invoke(MeetingQuotaPlan $plan, User $admin): MeetingQuotaPlan
    {
        if ($plan->status !== MeetingQuotaPlanStatus::Published) {
            throw MeetingQuotaPlanInvalidTransitionException::forArchive();
        }

        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => MeetingQuotaPlanStatus::Archived->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
