<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidInvitationTokenException;
use App\Http\Controllers\Auth\OnboardingController;
use App\Models\Invitation;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\UseCases\MeetingQuota\GrantInitialQuotaAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 招待を受領し、既存 invited User を受講中(in_progress)に遷移させ、自動ログインさせるユースケース。
 *
 * - status を invited → in_progress に更新し、Plan 期間(`plan_started_at` / `plan_expires_at`)を確定する
 * - コーチ宛て招待では `meeting_url` を必須項目として保存する(空文字 / 未指定は FormRequest で 422 に弾く前提)
 * - 初期付与の面談回数を MeetingQuotaTransaction(`granted_initial`)として起票する(残数集計の整合性のため `User.max_meetings`
 *   とは別経路で履歴を残す)
 * - 全 DB 操作は `DB::transaction()` で囲み、自動ログインは commit 後に実施する
 *
 * @see OnboardingController::store()
 */
final class OnboardAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly GrantInitialQuotaAction $grantInitial,
    ) {}

    /**
     * @param array{name: string, bio?: ?string, password: string, meeting_url?: string} $validated
     *
     * @throws InvalidInvitationTokenException
     */
    public function __invoke(Invitation $invitation, array $validated): User
    {
        $user = DB::transaction(function () use ($invitation, $validated) {
            $invitation->refresh();
            $user = $invitation->user;

            if (
                $user === null
                || $invitation->status !== InvitationStatus::Pending
                || $invitation->expires_at === null
                || $invitation->expires_at->isPast()
                || $user->status !== UserStatus::Invited
            ) {
                throw new InvalidInvitationTokenException;
            }

            // 受講生は Plan 必須(招待時に紐付け済み)。コーチは Plan を持たない。
            if ($user->role === UserRole::Student && $user->plan === null) {
                throw new InvalidInvitationTokenException;
            }

            $now = now();

            $attrs = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
                'password' => Hash::make($validated['password']),
                'profile_setup_completed' => true,
                'email_verified_at' => $now,
            ];

            // 受講生のみ Plan 期間を確定。コーチは受講期間という業務概念を持たない。
            if ($user->role === UserRole::Student) {
                $attrs['plan_started_at'] = $now;
                $attrs['plan_expires_at'] = $now->copy()->addDays($user->plan->duration_days);
            }

            if ($user->role === UserRole::Coach) {
                $attrs['meeting_url'] = $validated['meeting_url'];
            }

            // record() は遷移前 status を参照するため、status を変更する forceFill より前に呼ぶ
            $this->statusChanger->record(
                $user,
                UserStatus::InProgress,
                $user,
                'オンボーディング完了',
            );

            $user->forceFill($attrs)->save();

            // 面談クォータは受講生固有の消費対象。コーチは面談を提供する側のため初期付与しない。
            if ($user->role === UserRole::Student && $user->plan->default_meeting_quota > 0) {
                ($this->grantInitial)(
                    $user,
                    $user->plan->default_meeting_quota,
                    admin: null,
                    reason: 'オンボーディング初期付与',
                );
            }

            return $user->refresh();
        });

        Auth::login($user);

        return $user;
    }
}
