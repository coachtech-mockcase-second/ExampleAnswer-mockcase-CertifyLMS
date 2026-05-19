<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvitationNotPendingException;
use App\Models\Invitation;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\Services\UserWithdrawalService;
use App\UseCases\Invitation\DestroyAction;
use Illuminate\Support\Facades\DB;

/**
 * pending Invitation を revoke するユースケース。
 *
 * - 管理者操作の招待取消(`InvitationController::destroy` → `App\UseCases\Invitation\DestroyAction` 経由): cascadeWithdrawUser=true で User を connected withdrawn
 * - `IssueInvitationAction(force=true)` からの内部呼出: cascadeWithdrawUser=false で Invitation のみ revoke、User は invited のまま継続
 *
 * @see DestroyAction
 * @see IssueInvitationAction
 */
final class RevokeInvitationAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserWithdrawalService $withdrawalService,
    ) {}

    /**
     * pending Invitation を revoke する。
     *
     * - $cascadeWithdrawUser=true（admin 完全取消、デフォルト）: User を invited→withdrawn + soft delete + email リネーム + UserStatusLog 記録
     * - $cascadeWithdrawUser=false（IssueInvitationAction(force=true) からの内部呼出）: Invitation のみ revoke、User は invited のまま継続、UserStatusLog 記録なし
     *
     * @param ?User $admin 操作者。null ならシステム自動相当として UserStatusLog に記録される（$cascadeWithdrawUser=true の場合のみ意味あり）
     *
     * @throws InvitationNotPendingException 対象 Invitation が pending 以外の状態(accepted / expired / revoked)
     */
    public function __invoke(
        Invitation $invitation,
        ?User $admin = null,
        bool $cascadeWithdrawUser = true,
    ): void {
        DB::transaction(function () use ($invitation, $admin, $cascadeWithdrawUser) {
            if ($invitation->status !== InvitationStatus::Pending) {
                throw new InvitationNotPendingException;
            }

            $invitation->forceFill([
                'status' => InvitationStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            if (! $cascadeWithdrawUser) {
                return;
            }

            $user = $invitation->user;

            if ($user === null || $user->status !== UserStatus::Invited) {
                return;
            }

            // record() は遷移前 status を参照するため、必ず WithdrawalService より前に呼ぶ
            $this->statusChanger->record(
                $user,
                UserStatus::Withdrawn,
                $admin,
                '招待取消',
            );

            $this->withdrawalService->withdraw($user);
        });
    }
}
