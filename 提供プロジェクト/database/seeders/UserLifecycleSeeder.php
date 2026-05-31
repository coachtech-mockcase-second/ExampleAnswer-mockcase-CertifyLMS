<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\Models\UserStatusLog;
use Illuminate\Database\Seeder;

/**
 * 受講生のライフサイクル履歴(ステータス遷移ログ / プラン履歴 / 初期面談付与)を補完するシーダー。
 *
 * これらの履歴は実運用ではステータス変更 Action(UserStatusChangeService / UserPlanLogService /
 * GrantInitialQuotaAction)を通じてのみ記録される。一方シーダーは Factory で status / plan を直接セット
 * するため、その履歴が一切残らない。admin ユーザー詳細の「ステータス履歴」「プラン履歴」、面談回数履歴の
 * 「初期付与」行を実機で動作確認できるよう、現在の status / plan から逆算した履歴をここで一括補完する:
 *
 * - **UserStatusLog**: invited→in_progress / in_progress→graduated / in_progress→withdrawn の遷移を現在 status から逆算(管理者操作 / システム自動の両方を含む)。
 * - **UserPlanLog**: 受講登録(assigned)を全プラン保有者に、満了(expired)を卒業者に。延長(renewed) / 解約(canceled) は代表サンプルで補完し event_type 4 種を網羅。
 * - **MeetingQuotaTransaction(granted_initial)**: プラン保有者の初期面談枠付与。残数集計は `User.max_meetings` 側でカウント済みのため、本トランザクションは履歴表示専用(残数に影響しない)。
 *
 * 依存順序: UserSeeder → PlanSeeder(plan_started_at / plan_expires_at / max_meetings 確定)→ 本 Seeder。
 */
class UserLifecycleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('UserLifecycleSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->withTrashed()
            ->with('plan')
            ->get();

        foreach ($students as $student) {
            $this->recordStatusJourney($student, $admin);
            $this->recordPlanHistory($student, $admin);
            $this->recordInitialQuotaGrant($student);
        }

        $this->recordLifecycleSamples($admin);
    }

    /**
     * 現在の status から逆算したステータス遷移履歴を起票する(from_status は常に直前の実ステータス)。
     *
     * - invited: 遷移ログなし(初期状態)
     * - in_progress: invited → in_progress
     * - graduated: invited → in_progress → graduated
     * - withdrawn: invited → in_progress → withdrawn
     *
     * タイムラインは plan_started_at(受講開始) / plan_expires_at(卒業) / deleted_at(退会) を基準に過去順で並べる。
     */
    private function recordStatusJourney(User $student, User $admin): void
    {
        // invited のまま遷移していない受講生は履歴なし(初期状態のため遷移ログは発生しない)
        if ($student->status === UserStatus::Invited) {
            return;
        }

        $startedAt = $student->plan_started_at?->copy()
            ?? $student->deleted_at?->copy()->subDays(38)
            ?? ($student->created_at ?? now())->copy();

        // オンボーディング完了(本人操作 = システム記録): invited → in_progress
        UserStatusLog::factory()->bySystem()->create([
            'user_id' => $student->id,
            'from_status' => UserStatus::Invited->value,
            'to_status' => UserStatus::InProgress->value,
            'changed_at' => $startedAt,
            'changed_reason' => 'オンボーディング完了',
        ]);

        if ($student->status === UserStatus::Graduated) {
            // 受講期間満了による自動卒業(システム記録)
            UserStatusLog::factory()->bySystem()->create([
                'user_id' => $student->id,
                'from_status' => UserStatus::InProgress->value,
                'to_status' => UserStatus::Graduated->value,
                'changed_at' => ($student->plan_expires_at ?? now())->copy(),
                'changed_reason' => '受講期間満了',
            ]);
        }

        if ($student->status === UserStatus::Withdrawn) {
            // 管理者による退会処理
            UserStatusLog::factory()->create([
                'user_id' => $student->id,
                'from_status' => UserStatus::InProgress->value,
                'to_status' => UserStatus::Withdrawn->value,
                'changed_by_user_id' => $admin->id,
                'changed_at' => ($student->deleted_at ?? now())->copy(),
                'changed_reason' => '管理者による退会処理',
            ]);
        }
    }

    /**
     * プラン保有者に受講登録(assigned)履歴を、卒業者に満了(expired)履歴を起票する。
     */
    private function recordPlanHistory(User $student, User $admin): void
    {
        if ($student->plan_id === null) {
            return;
        }

        $startedAt = $student->plan_started_at?->copy() ?? ($student->created_at ?? now())->copy();
        $quota = $student->plan?->default_meeting_quota ?? $student->max_meetings;

        UserPlanLog::factory()->assigned()->create([
            'user_id' => $student->id,
            'plan_id' => $student->plan_id,
            'plan_started_at' => $startedAt,
            'plan_expires_at' => $student->plan_expires_at,
            'meeting_quota_initial' => $quota,
            'changed_by_user_id' => $admin->id,
            'occurred_at' => $startedAt,
            'changed_reason' => '受講登録',
        ]);

        if ($student->status === UserStatus::Graduated) {
            UserPlanLog::factory()->expired()->create([
                'user_id' => $student->id,
                'plan_id' => $student->plan_id,
                'plan_started_at' => $startedAt,
                'plan_expires_at' => $student->plan_expires_at,
                'meeting_quota_initial' => $quota,
                'changed_by_user_id' => null,
                'occurred_at' => ($student->plan_expires_at ?? now())->copy(),
                'changed_reason' => '受講期間満了',
            ]);
        }
    }

    /**
     * プラン保有者の初期面談枠付与(granted_initial)を起票する。
     *
     * 残数集計は `User.max_meetings` 側で計上済みのため、本トランザクションは type=granted_initial として
     * 残数 SUM から除外される(二重計上回避)。面談回数履歴の「初期付与」行を表示するための履歴専用レコード。
     */
    private function recordInitialQuotaGrant(User $student): void
    {
        if ($student->plan_id === null) {
            return;
        }

        $amount = $student->plan?->default_meeting_quota ?? $student->max_meetings ?? 0;
        if ($amount <= 0) {
            return;
        }

        MeetingQuotaTransaction::factory()->grantedInitial($amount)->create([
            'user_id' => $student->id,
            'occurred_at' => $student->plan_started_at ?? now(),
        ]);
    }

    /**
     * event_type を網羅するための代表サンプル(更新 renewed / 解約 canceled)を起票する。
     *
     * - renewed: 在籍の長い受講中受講生 1 名にコース延長履歴を 1 件追加。
     * - canceled: 退会済受講生 1 名に「過去にプラン保有 → 退会時に解約」のプラン履歴を補完。
     */
    private function recordLifecycleSamples(User $admin): void
    {
        $plan = Plan::query()
            ->where('status', PlanStatus::Published->value)
            ->orderBy('sort_order')
            ->first();

        // renewed: 開始が最も古い受講中受講生をコース延長サンプルとして 1 件補完
        $renewalTarget = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotNull('plan_id')
            ->orderBy('plan_started_at')
            ->with('plan')
            ->first();

        if ($renewalTarget !== null) {
            $startedAt = $renewalTarget->plan_started_at?->copy() ?? now();
            UserPlanLog::factory()->renewed()->create([
                'user_id' => $renewalTarget->id,
                'plan_id' => $renewalTarget->plan_id,
                'plan_started_at' => $startedAt,
                'plan_expires_at' => $renewalTarget->plan_expires_at,
                'meeting_quota_initial' => $renewalTarget->plan?->default_meeting_quota ?? $renewalTarget->max_meetings,
                'changed_by_user_id' => $admin->id,
                'occurred_at' => $startedAt->copy()->addDays(20),
                'changed_reason' => 'コース延長',
            ]);
        }

        // canceled: 退会済受講生に「プラン保有 → 退会で解約」の履歴を補完(現在 plan_id は NULL のため履歴のみ)
        $cancelTarget = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Withdrawn->value)
            ->withTrashed()
            ->orderBy('deleted_at')
            ->first();

        if ($cancelTarget !== null && $plan !== null) {
            $assignedAt = ($cancelTarget->deleted_at ?? now())->copy()->subDays(38);
            $expiresAt = $assignedAt->copy()->addDays($plan->duration_days);

            UserPlanLog::factory()->assigned()->create([
                'user_id' => $cancelTarget->id,
                'plan_id' => $plan->id,
                'plan_started_at' => $assignedAt,
                'plan_expires_at' => $expiresAt,
                'meeting_quota_initial' => $plan->default_meeting_quota,
                'changed_by_user_id' => $admin->id,
                'occurred_at' => $assignedAt,
                'changed_reason' => '受講登録',
            ]);

            UserPlanLog::factory()->canceled()->create([
                'user_id' => $cancelTarget->id,
                'plan_id' => $plan->id,
                'plan_started_at' => $assignedAt,
                'plan_expires_at' => $expiresAt,
                'meeting_quota_initial' => $plan->default_meeting_quota,
                'changed_by_user_id' => $admin->id,
                'occurred_at' => ($cancelTarget->deleted_at ?? now())->copy(),
                'changed_reason' => '退会に伴う解約',
            ]);
        }
    }
}
