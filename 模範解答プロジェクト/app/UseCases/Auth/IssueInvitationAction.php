<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserPlanLogEventType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\InvalidInvitationPlanException;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Services\UserPlanLogService;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 招待を発行するユースケース。受講生招待では Plan を起点に User を初期化し、コーチ招待では Plan を持たない User を作る。
 *
 * - 新規 email + 受講生: invited User INSERT(plan_id + max_meetings を Plan から複写) + UserStatusLog 記録 + UserPlanLog(assigned) + Invitation INSERT + Mail 送信
 * - 新規 email + コーチ: invited User INSERT(plan 関連カラムは NULL、max_meetings=0) + UserStatusLog 記録 + Invitation INSERT + Mail 送信(UserPlanLog は起票しない)
 * - 既存 invited User + pending 残存: force=false で例外、force=true で旧 pending を revoke して同 user_id に再発行
 * - 既存 invited User + pending 不在(期限切れ / revoked のみ): 既存 User を再利用して新 Invitation を発行(status 不変)
 * - 既存 in_progress / graduated User: EmailAlreadyRegisteredException
 *
 * Plan は受講生固有の概念(受講期間 + 初期面談回数の起点)で、コーチは面談を提供する側のため Plan を持たない。
 * Plan 期間(`plan_started_at` / `plan_expires_at`)は受講生でも invited 段階では NULL とし、オンボーディング完了時に
 * OnboardAction が確定する。
 *
 * @see \App\Http\Controllers\InvitationController::store()
 * @see \App\UseCases\Invitation\StoreAction
 * @see \App\UseCases\Invitation\ResendAction
 */
final class IssueInvitationAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserPlanLogService $planLog,
        private readonly RevokeInvitationAction $revokeInvitation,
    ) {}

    /**
     * @param  ?Plan  $plan  受講生招待では必須、コーチ招待では NULL を渡す
     * @param  User  $invitedBy  招待を発行する管理者(performed_by として UserStatusLog に記録される)
     * @param  bool  $force  既存 pending 招待があっても上書き発行するか(true で旧 pending を revoke)
     *
     * @throws EmailAlreadyRegisteredException
     * @throws PendingInvitationAlreadyExistsException
     * @throws InvalidInvitationPlanException 受講生招待で Plan 未指定 / コーチ招待で Plan 指定された場合
     */
    public function __invoke(
        string $email,
        UserRole $role,
        ?Plan $plan,
        User $invitedBy,
        bool $force = false,
    ): Invitation {
        // 受講生招待では Plan が必須、コーチ招待では Plan は許可しない(整合性ガード)
        if ($role === UserRole::Student && $plan === null) {
            throw InvalidInvitationPlanException::forStudentMissingPlan();
        }
        if ($role === UserRole::Coach && $plan !== null) {
            throw InvalidInvitationPlanException::forCoachWithPlan();
        }

        return DB::transaction(function () use ($email, $role, $plan, $invitedBy, $force) {
            $registeredUser = User::where('email', $email)
                ->whereIn('status', [UserStatus::InProgress, UserStatus::Graduated])
                ->first();

            if ($registeredUser !== null) {
                throw new EmailAlreadyRegisteredException;
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
                        throw new PendingInvitationAlreadyExistsException;
                    }

                    // force=true: 旧 pending を revoke。User は invited のまま継続させるため cascade なし。
                    ($this->revokeInvitation)(
                        $pendingInvitation,
                        admin: null,
                        cascadeWithdrawUser: false,
                    );
                }

                // 受講生のみ Plan 切替時にカラム更新 + UserPlanLog(assigned) 起票。コーチは Plan を持たないのでスキップ。
                $planChanged = $plan !== null && $invitedUser->plan_id !== $plan->id;

                if ($planChanged) {
                    $invitedUser->forceFill([
                        'plan_id' => $plan->id,
                        'max_meetings' => $plan->default_meeting_quota,
                    ])->save();
                }

                $user = $invitedUser;
                $shouldLogPlanAssignment = $planChanged;
            } else {
                $user = User::create([
                    'email' => $email,
                    'role' => $role->value,
                    'status' => UserStatus::Invited->value,
                    'password' => null,
                    'name' => null,
                    'profile_setup_completed' => false,
                    'plan_id' => $plan?->id,
                    'plan_started_at' => null,
                    'plan_expires_at' => null,
                    'max_meetings' => $plan?->default_meeting_quota ?? 0,
                ]);

                $this->statusChanger->record(
                    $user,
                    UserStatus::Invited,
                    $invitedBy,
                    '新規招待',
                );

                // 新規 invited User の場合、受講生(Plan あり)なら UserPlanLog 起票、コーチ(Plan なし)なら起票しない
                $shouldLogPlanAssignment = $plan !== null;
            }

            if ($shouldLogPlanAssignment && $plan !== null) {
                $this->planLog->record($user, $plan, UserPlanLogEventType::Assigned, $invitedBy, '招待発行');
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
