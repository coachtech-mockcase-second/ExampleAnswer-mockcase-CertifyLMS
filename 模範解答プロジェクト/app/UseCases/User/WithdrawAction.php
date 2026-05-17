<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\UserManagement\LastAdminWithdrawException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\Services\UserWithdrawalService;
use Illuminate\Support\Facades\DB;

/**
 * 管理者操作によるユーザー強制退会ユースケース。
 *
 * - 退会済 User の再退会は `UserAlreadyWithdrawnException`(409) で拒否(冪等にしない)
 * - 退会対象が admin ロールで、残存 admin が 0 になる場合は `LastAdminWithdrawException`(409) で拒否
 * - 上記ガード通過後、`UserStatusLog` 記録 → `UserWithdrawalService` で email リネーム + status=Withdrawn + soft delete
 * - 「強制退会」ボタンは admin 操作画面で `in_progress` / `graduated` の User のみ表示する設計(`invited` は招待取消経路に誘導)
 *
 * @see \App\Http\Controllers\UserController::withdraw()
 */
final class WithdrawAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserWithdrawalService $withdrawalService,
    ) {}

    /**
     * @param  ?User  $admin  操作者。Schedule Command 等のシステム自動経路では null
     *
     * @throws UserAlreadyWithdrawnException
     * @throws LastAdminWithdrawException
     */
    public function __invoke(User $user, ?User $admin = null): void
    {
        if ($user->status === UserStatus::Withdrawn) {
            throw new UserAlreadyWithdrawnException;
        }

        if ($this->wouldRemoveLastAdmin($user)) {
            throw new LastAdminWithdrawException;
        }

        DB::transaction(function () use ($user, $admin) {
            // record() は遷移前 status を参照するため、必ず WithdrawalService の更新より前に呼ぶ
            $this->statusChanger->record($user, UserStatus::Withdrawn, $admin, '管理者による退会');

            $this->withdrawalService->withdraw($user);
        });
    }

    private function wouldRemoveLastAdmin(User $user): bool
    {
        if ($user->role !== UserRole::Admin) {
            return false;
        }

        $remainingAdmins = User::query()
            ->where('role', UserRole::Admin)
            ->where('status', '!=', UserStatus::Withdrawn)
            ->where('id', '!=', $user->id)
            ->count();

        return $remainingAdmins === 0;
    }
}
