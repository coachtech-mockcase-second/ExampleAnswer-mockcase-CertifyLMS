<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 追加面談 SKU マスタ詳細取得ユースケース。createdBy / updatedBy / 直近の Payment を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->load([
            'createdBy',
            'updatedBy',
            'payments' => fn ($q) => $q->latest()->limit(20)->with('user'),
        ]);
    }
}
