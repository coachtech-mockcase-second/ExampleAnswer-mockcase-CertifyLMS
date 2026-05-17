<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 Plan + 受講生 × Plan 紐づけシーダー。
 *
 * **設計思想（Seeder 業界標準: 状態網羅 + 固定 + 多様な進捗）**:
 *
 * 1. Plan マスタを **status 網羅** で投入(published × 3 + draft × 1 + archived × 1)。
 *    一覧画面のフィルタ・状態遷移ボタンの活性条件を実機確認するため。
 *
 * 2. `UserSeeder` が作った in_progress / graduated student に対し、**期限進捗を多様化**して Plan を紐づける。
 *    - student@certify-lms.test (固定): 受講開始直後の 1 ヶ月プラン
 *    - in_progress demo students × 8: 開始直後 / 中盤 / 期限直前 を散らす(Sequence で進捗位置を回す)
 *    - graduated students × 3: plan_expires_at が過去 (UserSeeder で確定済)
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('PlanSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $publishedPlans = $this->createPublishedPlans($admin);
        $this->createNonPublishedPlans($admin);
        $this->assignPlansToStudents($publishedPlans);
    }

    /**
     * 受講生招待で実利用される published Plan を 3 種投入。
     *
     * @return array<int, Plan>
     */
    private function createPublishedPlans(User $admin): array
    {
        $data = [
            ['name' => '1 ヶ月プラン 4 回', 'duration_days' => 30, 'default_meeting_quota' => 4, 'sort_order' => 10, 'description' => '短期集中で資格取得を目指す方向け。月 4 回の面談付き。'],
            ['name' => '3 ヶ月プラン 12 回', 'duration_days' => 90, 'default_meeting_quota' => 12, 'sort_order' => 20, 'description' => '標準的な学習期間。週 1 回ペースで面談が可能。'],
            ['name' => '6 ヶ月プラン 24 回', 'duration_days' => 180, 'default_meeting_quota' => 24, 'sort_order' => 30, 'description' => '腰を据えてじっくり学びたい方向け。半年間で 24 回の面談。'],
        ];

        $plans = [];
        foreach ($data as $row) {
            $plans[] = Plan::factory()
                ->state([
                    ...$row,
                    'status' => PlanStatus::Published->value,
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                ])
                ->create();
        }

        return $plans;
    }

    /**
     * draft / archived Plan も少数投入(admin の Plan 一覧で status フィルタが効くことを実機確認するため)。
     */
    private function createNonPublishedPlans(User $admin): void
    {
        Plan::factory()
            ->draft()
            ->state([
                'name' => '新プラン(検討中)',
                'description' => '次期リリース予定の長期プラン。料金プランは確定後に公開。',
                'duration_days' => 365,
                'default_meeting_quota' => 36,
                'sort_order' => 40,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();

        Plan::factory()
            ->archived()
            ->state([
                'name' => '旧 2 ヶ月プラン 6 回',
                'description' => '2025 年度まで提供。新規受付終了。',
                'duration_days' => 60,
                'default_meeting_quota' => 6,
                'sort_order' => 99,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ])
            ->create();
    }

    /**
     * 受講中 student に Plan を紐づけ、期限進捗を多様化する。
     *
     * @param  array<int, Plan>  $publishedPlans
     */
    private function assignPlansToStudents(array $publishedPlans): void
    {
        [$plan1mo, $plan3mo, $plan6mo] = $publishedPlans;

        // 固定 student: 開始直後の 1 ヶ月プラン
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($fixedStudent !== null) {
            $this->attachPlanWithProgress($fixedStudent, $plan1mo, daysSinceStart: 2);
        }

        // demo in_progress students × 8 を 3 種プランに散らし、進捗位置も多様化
        $inProgressStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->whereNull('plan_id')
            ->get();

        $scenarios = [
            ['plan' => $plan1mo, 'daysSinceStart' => 25],  // 1ヶ月プラン、期限直前(残り5日)
            ['plan' => $plan1mo, 'daysSinceStart' => 10],  // 1ヶ月プラン、中盤
            ['plan' => $plan3mo, 'daysSinceStart' => 80],  // 3ヶ月プラン、期限直前(残り10日)
            ['plan' => $plan3mo, 'daysSinceStart' => 45],  // 3ヶ月プラン、中盤
            ['plan' => $plan3mo, 'daysSinceStart' => 5],   // 3ヶ月プラン、開始直後
            ['plan' => $plan6mo, 'daysSinceStart' => 150], // 6ヶ月プラン、期限直前(残り30日)
            ['plan' => $plan6mo, 'daysSinceStart' => 90],  // 6ヶ月プラン、中盤
            ['plan' => $plan6mo, 'daysSinceStart' => 15],  // 6ヶ月プラン、開始直後
        ];

        foreach ($inProgressStudents as $index => $student) {
            $scenario = $scenarios[$index] ?? $scenarios[0];
            $this->attachPlanWithProgress($student, $scenario['plan'], $scenario['daysSinceStart']);
        }

        // graduated students にも過去の Plan を紐づける(修了証画面・卒業履歴の表示確認用)
        $graduatedStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Graduated->value)
            ->whereNull('plan_id')
            ->get();

        foreach ($graduatedStudents as $index => $student) {
            $plan = $publishedPlans[$index % count($publishedPlans)];
            // plan_expires_at は UserSeeder で過去日付に確定済み。plan_started_at + max_meetings のみ補完。
            $student->update([
                'plan_id' => $plan->id,
                'plan_started_at' => $student->plan_expires_at->copy()->subDays($plan->duration_days),
                'max_meetings' => $plan->default_meeting_quota,
            ]);
        }
    }

    /**
     * 指定 student に Plan を紐づけ、開始日 = now() - $daysSinceStart で進捗を表現する。
     */
    private function attachPlanWithProgress(User $student, Plan $plan, int $daysSinceStart): void
    {
        $startedAt = now()->subDays($daysSinceStart);

        $student->update([
            'plan_id' => $plan->id,
            'plan_started_at' => $startedAt,
            'plan_expires_at' => $startedAt->copy()->addDays($plan->duration_days),
            'max_meetings' => $plan->default_meeting_quota,
        ]);
    }
}
