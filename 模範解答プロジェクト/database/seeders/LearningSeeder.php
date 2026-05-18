<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use App\Models\LearningSession;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 学習活動の demo データ シーダー。
 *
 * 状態網羅 + 固定アカウントの 2 軸で投入する:
 *
 * 1. 固定アカウント `student@certify-lms.test`: 直近 7 日間に学習セッションを毎日 1 件 closed で投入し、
 *    StreakService の連続学習日数 7 日 / 直近活動日 = 今日 を再現する。学習時間目標も 1 件設定して
 *    `learning/hour-target` 画面の表示確認用素材とする。
 *
 * 2. 状態網羅 demo データ: learning 状態の全 demo Enrollment に対し、配下 Section の 30〜70% に
 *    SectionProgress を投入(進捗ゲージのバリエーション)+ 学習セッションを active / closed / autoClosed の
 *    3 状態で混在配置する。Schedule Command の auto-close 対象を再現するため `started_at` を 2 時間以上前にした
 *    open セッションを最低 1 件含める。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `ContentSeeder` → 本 Seeder。
 */
final class LearningSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($fixedStudent !== null) {
            $this->seedForFixedStudent($fixedStudent);
        }

        $this->seedForLearningEnrollments();
    }

    private function seedForFixedStudent(User $student): void
    {
        $studentEnrollments = $student->enrollments()
            ->where('status', EnrollmentStatus::Learning->value)
            ->get();

        foreach ($studentEnrollments as $enrollment) {
            $this->seedSectionProgresses($enrollment, ratio: 0.4);
            $this->seedDailyStreak($enrollment, days: 7);

            LearningHourTarget::factory()
                ->forEnrollment($enrollment)
                ->hours(120)
                ->create();
        }
    }

    private function seedForLearningEnrollments(): void
    {
        $enrollments = Enrollment::query()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->whereDoesntHave('user', fn ($q) => $q->where('email', 'student@certify-lms.test'))
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            $ratio = 0.3 + ($index % 5) * 0.1;
            $this->seedSectionProgresses($enrollment, $ratio);

            $closed = max(2, $index % 5);
            LearningSession::factory()
                ->forUser($enrollment->user)
                ->forEnrollment($enrollment)
                ->count($closed)
                ->closed(1800)
                ->create();

            if ($index % 3 === 0) {
                LearningSession::factory()
                    ->forUser($enrollment->user)
                    ->forEnrollment($enrollment)
                    ->autoClosed(3600)
                    ->create();
            }

            if ($index % 7 === 0) {
                LearningSession::factory()
                    ->forUser($enrollment->user)
                    ->forEnrollment($enrollment)
                    ->state([
                        'started_at' => now()->subHours(3),
                        'ended_at' => null,
                        'duration_seconds' => null,
                        'auto_closed' => false,
                    ])
                    ->create();
            }
        }
    }

    private function seedSectionProgresses(Enrollment $enrollment, float $ratio): void
    {
        $sections = Section::query()
            ->whereHas('chapter.part', fn ($q) => $q->where('certification_id', $enrollment->certification_id))
            ->inRandomOrder()
            ->get();

        $count = (int) floor($sections->count() * $ratio);
        $targets = $sections->take($count);

        foreach ($targets as $section) {
            SectionProgress::factory()
                ->forEnrollment($enrollment)
                ->forSection($section)
                ->create();
        }
    }

    private function seedDailyStreak(Enrollment $enrollment, int $days): void
    {
        $sections = Section::query()
            ->whereHas('chapter.part', fn ($q) => $q->where('certification_id', $enrollment->certification_id))
            ->limit($days)
            ->get();

        if ($sections->isEmpty()) {
            return;
        }

        foreach (range(0, $days - 1) as $daysAgo) {
            $section = $sections->get($daysAgo % $sections->count());
            LearningSession::factory()
                ->forUser($enrollment->user)
                ->forEnrollment($enrollment)
                ->forSection($section)
                ->state([
                    'started_at' => now()->subDays($daysAgo)->setTime(20, 0),
                    'ended_at' => now()->subDays($daysAgo)->setTime(20, 30),
                    'duration_seconds' => 1800,
                    'auto_closed' => false,
                ])
                ->create();
        }
    }
}
