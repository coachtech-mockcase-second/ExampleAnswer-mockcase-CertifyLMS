<?php

declare(strict_types=1);

namespace App\UseCases\Invitation;

use App\Http\Controllers\InvitationController;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\RevokeInvitationAction;

/**
 * 管理者の招待取消 UI (`InvitationController::destroy`) から呼ばれるラッパー Action。
 *
 * 認証基盤の `RevokeInvitationAction` に委譲し、cascade で User も withdrawn にする。
 *
 * @see InvitationController::destroy()
 */
final class DestroyAction
{
    public function __construct(private readonly RevokeInvitationAction $revoke) {}

    public function __invoke(Invitation $invitation, User $admin): void
    {
        ($this->revoke)($invitation, $admin, cascadeWithdrawUser: true);
    }
}
