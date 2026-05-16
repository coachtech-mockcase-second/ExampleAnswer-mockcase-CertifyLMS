<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserStatus;
use App\Exceptions\UserManagement\InvitedUserWithdrawNotAllowedException;
use App\Exceptions\UserManagement\SelfWithdrawForbiddenException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\Services\UserWithdrawalService;
use Illuminate\Support\Facades\DB;

class WithdrawAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserWithdrawalService $withdrawalService,
    ) {}

    /**
     * admin によるユーザー退会処理。
     * 認可（admin 確認）は Controller の $this->authorize('withdraw', $user) で完結済の前提。
     * Policy 集約（self 禁止 / withdrawn 不変）は段階 5（user-management v3 改修）で完了予定、現状はガードを Action 内に残す。
     *
     * @throws SelfWithdrawForbiddenException 自分自身を退会させようとした
     * @throws UserAlreadyWithdrawnException 対象が既に退会済み
     * @throws InvitedUserWithdrawNotAllowedException 招待中ユーザーは本 Action では退会させない（招待取消動線を使う）
     */
    public function __invoke(User $user, User $admin, string $reason): void
    {
        if ($user->is($admin)) {
            throw new SelfWithdrawForbiddenException;
        }

        if ($user->status === UserStatus::Withdrawn) {
            throw new UserAlreadyWithdrawnException;
        }

        if ($user->status === UserStatus::Invited) {
            throw new InvitedUserWithdrawNotAllowedException;
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            $this->withdrawalService->withdraw($user);
            $this->statusChanger->record($user, UserStatus::Withdrawn, $admin, $reason);
        });
    }
}
