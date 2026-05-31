<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Http\Controllers\UserController;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\UseCases\MeetingQuota\AdminGrantQuotaAction;

/**
 * 管理者操作のユーザー詳細画面「面談回数手動付与」(`UserController::grantMeetingQuota`)から呼ばれるラッパー Action。
 *
 * 付与処理本体は MeetingQuota ドメインの `\App\UseCases\MeetingQuota\AdminGrantQuotaAction` に委譲する。
 * 本ラッパーは「Controller method 名 = 同 Feature の Action クラス名」規約を保つために配置する。
 *
 * @see UserController::grantMeetingQuota()
 */
final class GrantMeetingQuotaAction
{
    public function __construct(
        private readonly AdminGrantQuotaAction $grant,
    ) {}

    public function __invoke(User $user, int $amount, User $admin, ?string $reason): MeetingQuotaTransaction
    {
        return ($this->grant)($user, $amount, $admin, $reason);
    }
}
