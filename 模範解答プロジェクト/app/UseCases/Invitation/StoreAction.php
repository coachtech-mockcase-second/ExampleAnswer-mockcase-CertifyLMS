<?php

declare(strict_types=1);

namespace App\UseCases\Invitation;

use App\Enums\UserRole;
use App\Http\Controllers\InvitationController;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;

/**
 * 管理者の招待発行 UI(`InvitationController::store`)から呼ばれるラッパー Action。
 * 受講生招待では Plan を受け取り、コーチ招待では Plan=NULL を受け取って認証基盤の `IssueInvitationAction` に委譲する。
 *
 * @see InvitationController::store()
 */
final class StoreAction
{
    public function __construct(private readonly IssueInvitationAction $issue) {}

    /**
     * @param ?Plan $plan 受講生招待では必須、コーチ招待では NULL
     */
    public function __invoke(string $email, UserRole $role, ?Plan $plan, User $admin): Invitation
    {
        return ($this->issue)($email, $role, $plan, $admin, force: false);
    }
}
