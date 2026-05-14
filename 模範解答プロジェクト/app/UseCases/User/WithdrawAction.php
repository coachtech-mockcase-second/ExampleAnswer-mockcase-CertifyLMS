<?php

namespace App\UseCases\User;

use App\Enums\UserStatus;
use App\Exceptions\UserManagement\SelfWithdrawForbiddenException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WithdrawAction
{
    public function __construct(private UserStatusChangeService $statusChanger)
    {
    }

    public function __invoke(User $user, User $admin, string $reason): void
    {
        if ($user->is($admin)) {
            throw new SelfWithdrawForbiddenException();
        }

        if ($user->status === UserStatus::Withdrawn) {
            throw new UserAlreadyWithdrawnException();
        }

        if ($user->status === UserStatus::Invited) {
            throw new HttpException(422, '招待中ユーザーは「招待を取消」から削除してください。');
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            $user->withdraw();
            $this->statusChanger->record($user, UserStatus::Withdrawn, $admin, $reason);
        });
    }
}
