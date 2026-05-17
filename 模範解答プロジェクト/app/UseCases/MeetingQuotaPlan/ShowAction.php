<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuotaPlan;

use App\Models\MeetingQuotaPlan;

/**
 * 追加面談 SKU マスタ詳細取得ユースケース。createdBy / updatedBy / 直近の Payment を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(MeetingQuotaPlan $plan): MeetingQuotaPlan
    {
        return $plan->load([
            'createdBy',
            'updatedBy',
            'payments' => fn ($q) => $q->latest()->limit(20)->with('user'),
        ]);
    }
}
