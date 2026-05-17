<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\UserPlanLogEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserPlanLogService;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\DB;

/**
 * 期限満了による自動卒業ユースケース。Schedule Command(users:graduate-expired)から呼ばれる。
 *
 * - User.status = Graduated に UPDATE(deleted_at は変更しない — graduated はログイン可)
 * - UserStatusChangeService::record(Graduated, システム自動)
 * - UserPlanLogService::record(Expired, システム自動)
 *
 * 通知は発火しない（プラン期限間近通知 / 卒業通知ともに本 LMS の通知設計対象外）。
 */
final class GraduateUserAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserPlanLogService $planLog,
    ) {}

    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update(['status' => UserStatus::Graduated->value]);

            $this->statusChanger->record($user, UserStatus::Graduated, null, '期限満了による自動卒業');

            if ($user->plan !== null) {
                $this->planLog->record($user, $user->plan, UserPlanLogEventType::Expired, null, '期限満了');
            }
        });
    }
}
