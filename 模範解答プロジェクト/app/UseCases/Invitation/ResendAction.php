<?php

declare(strict_types=1);

namespace App\UseCases\Invitation;

use App\Enums\UserRole;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;
use RuntimeException;

/**
 * 既存 invited User への再招待 UI(`InvitationController::resend`)から呼ばれるラッパー Action。
 *
 * 招待発行時に紐付けた Plan を維持したまま、`IssueInvitationAction` に `force=true` で渡すことで
 * 既存 pending Invitation を revoke してから新しい Invitation を発行する。
 *
 * @see \App\Http\Controllers\InvitationController::resend()
 */
final class ResendAction
{
    public function __construct(private readonly IssueInvitationAction $issue) {}

    /**
     * @throws PendingInvitationAlreadyExistsException
     */
    public function __invoke(User $user, User $admin): Invitation
    {
        $plan = $user->plan;

        // 受講生は招待発行時に必ず Plan が紐付けられる仕様。コーチは Plan を持たない。
        if ($user->role === UserRole::Student && ! $plan instanceof Plan) {
            throw new RuntimeException('再招待対象の受講生に Plan が紐付いていません。');
        }

        // コーチの場合は plan を null として渡す
        $planForReinvite = $user->role === UserRole::Coach ? null : $plan;

        return ($this->issue)($user->email, $user->role, $planForReinvite, $admin, force: true);
    }
}
