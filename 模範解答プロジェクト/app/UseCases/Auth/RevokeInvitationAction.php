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
use Illuminate\Support\Facades\DB;

class RevokeInvitationAction
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

            $this->withdrawalService->withdraw($user);

            $this->statusChanger->record(
                $user,
                UserStatus::Withdrawn,
                $admin,
                '招待取消',
            );
        });
    }
}
