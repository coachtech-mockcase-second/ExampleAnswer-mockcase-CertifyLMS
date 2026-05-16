<?php

declare(strict_types=1);

namespace App\UseCases\Invitation;

use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;

class ResendAction
{
    public function __construct(private readonly IssueInvitationAction $issue) {}

    public function __invoke(User $user, User $admin): Invitation
    {
        return ($this->issue)($user->email, $user->role, $admin, force: true);
    }
}
