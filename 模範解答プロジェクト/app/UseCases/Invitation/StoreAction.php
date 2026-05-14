<?php

namespace App\UseCases\Invitation;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;

class StoreAction
{
    public function __construct(private IssueInvitationAction $issue)
    {
    }

    public function __invoke(string $email, UserRole $role, User $admin): Invitation
    {
        return ($this->issue)($email, $role, $admin, force: false);
    }
}
