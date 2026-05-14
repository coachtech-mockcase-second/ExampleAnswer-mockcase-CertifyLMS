<?php

namespace App\UseCases\Invitation;

use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\RevokeInvitationAction;

class DestroyAction
{
    public function __construct(private RevokeInvitationAction $revoke)
    {
    }

    public function __invoke(Invitation $invitation, User $admin): void
    {
        ($this->revoke)($invitation, $admin, cascadeWithdrawUser: true);
    }
}
