<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * ユーザー退会の物理的処理を集約する Service。
 *
 * email を `{ulid}@deleted.invalid` 形式へリネーム + status=Withdrawn + soft delete を 1 操作で行う。
 * 呼出側 Action（`WithdrawAction` / `RevokeInvitationAction` / `ExpireInvitationsAction`）が
 * 同一トランザクション内で `UserStatusChangeService::record()` も呼んで監査ログを記録する契約。
 *
 * 退会処理を Active Record に持たせず Service に集約することで、退会経路の一元管理と
 * 呼出側 Action の transaction 境界を明示化する。
 */
final class UserWithdrawalService
{
    /**
     * ユーザーを退会状態にする（email リネーム + status 更新 + soft delete）。
     *
     * 監査ログ（UserStatusLog）の記録は呼出側 Action の責務。
     * 本メソッドは `DB::transaction` を持たない（呼出側で囲む）。
     */
    public function withdraw(User $user): void
    {
        $user->forceFill([
            'email' => $user->id.'@deleted.invalid',
            'status' => UserStatus::Withdrawn,
        ])->save();

        $user->delete();
    }
}
