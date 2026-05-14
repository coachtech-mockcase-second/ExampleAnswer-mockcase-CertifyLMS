<?php

namespace App\UseCases\Invitation;

use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;

class ResendAction
{
    public function __construct(private IssueInvitationAction $issue)
    {
    }

    public function __invoke(User $user, User $admin): Invitation
    {
        return ($this->issue)($user->email, $user->role, $admin, force: true);
    }
}
