<?php

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IssueInvitationAction
{
    public function __construct(
        private UserStatusChangeService $statusChanger,
        private RevokeInvitationAction $revokeInvitation,
    ) {
    }

    /**
     * 招待を発行する。
     *
     * - 同 email の active User が存在: EmailAlreadyRegisteredException
     * - 同 email の invited User + 未期限切れ pending Invitation が存在:
     *     - force=false → PendingInvitationAlreadyExistsException
     *     - force=true  → 旧 pending を revoke（cascade なし、User は invited のまま）し、同じ user_id へ新 Invitation を発行
     * - 同 email の invited User が存在するが pending Invitation が無い（期限切れ等で expired/revoked のみ）: 既存 User を再利用して新 Invitation を発行（status 変化なしのため UserStatusLog 新規挿入なし）
     * - 同 email の User が無い: 新規 invited User INSERT + UserStatusLog 記録 + Invitation INSERT
     */
    public function __invoke(
        string $email,
        UserRole $role,
        User $invitedBy,
        bool $force = false,
    ): Invitation {
        return DB::transaction(function () use ($email, $role, $invitedBy, $force) {
            $activeUser = User::where('email', $email)
                ->where('status', UserStatus::Active)
                ->first();

            if ($activeUser !== null) {
                throw new EmailAlreadyRegisteredException();
            }

            $invitedUser = User::where('email', $email)
                ->where('status', UserStatus::Invited)
                ->first();

            if ($invitedUser !== null) {
                $pendingInvitation = $invitedUser->invitations()
                    ->pending()
                    ->where('expires_at', '>', now())
                    ->first();

                if ($pendingInvitation !== null) {
                    if (! $force) {
                        throw new PendingInvitationAlreadyExistsException();
                    }

                    // force=true: 旧 pending を revoke（cascade なし、UserStatusLog 記録なし）
                    ($this->revokeInvitation)(
                        $pendingInvitation,
                        admin: null,
                        cascadeWithdrawUser: false,
                    );
                }

                $user = $invitedUser;
                // 既存 invited User の再利用: status 変化なし → UserStatusLog 新規挿入なし
            } else {
                $user = User::create([
                    'email' => $email,
                    'role' => $role->value,
                    'status' => UserStatus::Invited->value,
                    'password' => null,
                    'name' => null,
                    'profile_setup_completed' => false,
                ]);

                $this->statusChanger->record(
                    $user,
                    UserStatus::Invited,
                    $invitedBy,
                    '新規招待',
                );
            }

            $invitation = $user->invitations()->create([
                'email' => $email,
                'role' => $role->value,
                'invited_by_user_id' => $invitedBy->id,
                'expires_at' => now()->addDays(config('auth.invitation_expire_days', 7)),
                'status' => InvitationStatus::Pending->value,
            ]);

            Mail::send(new InvitationMail($invitation));

            return $invitation;
        });
    }
}
