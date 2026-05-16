<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\User;

class ShowAction
{
    public function __invoke(User $user): User
    {
        return $user->load([
            'statusLogs' => fn ($q) => $q->orderByDesc('changed_at'),
            'statusLogs.changedBy' => fn ($q) => $q->withTrashed(),
            'invitations' => fn ($q) => $q->orderByDesc('created_at'),
            'invitations.invitedBy' => fn ($q) => $q->withTrashed(),
        ]);
    }
}
