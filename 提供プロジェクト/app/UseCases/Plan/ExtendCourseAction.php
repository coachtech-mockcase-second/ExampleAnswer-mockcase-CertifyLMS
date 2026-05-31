<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Enums\UserPlanLogEventType;
use App\Enums\UserStatus;
use App\Exceptions\Plan\PlanNotPublishedException;
use App\Exceptions\Plan\UserNotInProgressException;
use App\Models\Plan;
use App\Models\User;
use App\Services\UserPlanLogService;
use App\UseCases\MeetingQuota\GrantInitialQuotaAction;
use Illuminate\Support\Facades\DB;

/**
 * プラン延長ユースケース。受講中ユーザーに対し、既存期限を起点に duration_days 加算 + 面談回数加算 + 履歴記録 + 面談回数付与を実施。
 *
 * - User.status != InProgress の場合は UserNotInProgressException(再加入は新規招待)
 * - Plan.status != Published の場合は PlanNotPublishedException
 * - 期限加算は既存 plan_expires_at(NULL なら now())起点で plan->duration_days 日加算
 * - 面談回数は User.max_meetings に plan->default_meeting_quota を加算
 * - UserPlanLogService::record(Renewed) + GrantInitialQuotaAction(面談回数付与) を呼出
 *
 * 管理者 admin ユーザー一覧の「プラン延長」操作から、user-management 側の Controller ラッパーを介して呼ばれる。
 */
final class ExtendCourseAction
{
    public function __construct(
        private readonly UserPlanLogService $planLog,
        private readonly GrantInitialQuotaAction $grantQuota,
    ) {}

    /**
     * @throws UserNotInProgressException
     * @throws PlanNotPublishedException
     */
    public function __invoke(
        User $user,
        Plan $plan,
        ?User $admin = null,
        ?string $reason = null,
    ): User {
        return DB::transaction(function () use ($user, $plan, $admin, $reason) {
            if ($user->status !== UserStatus::InProgress) {
                throw new UserNotInProgressException;
            }

            if ($plan->status !== PlanStatus::Published) {
                throw new PlanNotPublishedException;
            }

            $base = $user->plan_expires_at ?? now();
            $user->plan_expires_at = $base->copy()->addDays($plan->duration_days);
            $user->max_meetings = $user->max_meetings + $plan->default_meeting_quota;
            $user->save();

            $this->planLog->record($user, $plan, UserPlanLogEventType::Renewed, $admin, $reason);

            ($this->grantQuota)($user, $plan->default_meeting_quota, $admin, $reason ?? 'プラン延長');

            return $user->fresh();
        });
    }
}
