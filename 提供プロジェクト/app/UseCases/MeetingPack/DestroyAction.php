<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingQuota\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談 SKU マスタ削除ユースケース。published 状態は削除不可、draft / archived のみ SoftDelete。
 */
final class DestroyAction
{
    /**
     * @throws MeetingPackNotDeletableException
     */
    public function __invoke(MeetingPack $plan): void
    {
        if ($plan->status === MeetingPackStatus::Published) {
            throw new MeetingPackNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
