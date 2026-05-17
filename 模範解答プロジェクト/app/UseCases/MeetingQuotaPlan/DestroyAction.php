<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Enums\MeetingQuotaPlanStatus;
use App\Exceptions\MeetingQuota\MeetingQuotaPlanNotDeletableException;
use App\Models\MeetingQuotaPlan;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタ削除ユースケース。published 状態は削除不可、draft / archived のみ SoftDelete。
 */
final class DestroyAction
{
    /**
     * @throws MeetingQuotaPlanNotDeletableException
     */
    public function __invoke(MeetingQuotaPlan $plan): void
    {
        if ($plan->status === MeetingQuotaPlanStatus::Published) {
            throw new MeetingQuotaPlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
