<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserPlanLogEventType;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;

/**
 * User × Plan の遷移を user_plan_logs テーブルに記録する Service。
 * Plan 関連の各 Action（StoreAction / ExtendCourseAction / GraduateUserAction）と
 * Auth の OnboardAction（プラン割当時）から呼ばれる。
 *
 * INSERT only(履歴は不可逆)。状態更新は呼出側 Action の責務。
 */
final class UserPlanLogService
{
    /**
     * UserPlanLog を INSERT する。User.plan_started_at / plan_expires_at が NULL の場合は now() で補完。
     *
     * @param User $user 履歴対象 User(呼出側が plan_* 系列を更新済の前提)
     * @param Plan $plan 該当 Plan
     * @param UserPlanLogEventType $eventType 遷移種別
     * @param ?User $changedBy 操作者。NULL はシステム自動(Schedule Command)
     * @param ?string $reason 理由(200 文字以内)
     */
    public function record(
        User $user,
        Plan $plan,
        UserPlanLogEventType $eventType,
        ?User $changedBy = null,
        ?string $reason = null,
    ): UserPlanLog {
        return UserPlanLog::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'event_type' => $eventType->value,
            'plan_started_at' => $user->plan_started_at ?? now(),
            'plan_expires_at' => $user->plan_expires_at ?? now(),
            'meeting_quota_initial' => $plan->default_meeting_quota,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
