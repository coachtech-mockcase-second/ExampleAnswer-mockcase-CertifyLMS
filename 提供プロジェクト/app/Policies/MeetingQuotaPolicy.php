<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * 受講生本人の面談回数履歴閲覧に関する認可。
 */
class MeetingQuotaPolicy
{
    /**
     * 面談回数履歴の閲覧。本人のみ可。
     */
    public function viewHistory(User $auth, User $target): bool
    {
        return $auth->id === $target->id;
    }
}
