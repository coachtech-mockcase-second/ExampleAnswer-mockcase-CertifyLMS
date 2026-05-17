<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 受講登録シーダー。固定 student@certify-lms.test に published 資格 1-2 件を learning で登録 +
 * 各 status を網羅した demo 受講登録を Factory で散らす(admin 一覧画面の status / 検索フィルタを実機確認するため)。
 */
final class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $publishedCertifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->get();

        if ($publishedCertifications->isEmpty()) {
            $this->command?->warn('EnrollmentSeeder: 公開済資格がありません。先に CertificationSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNot('email', 'student@certify-lms.test')
            ->limit(8)
            ->get();

        if ($fixedStudent === null && $demoStudents->isEmpty()) {
            $this->command?->warn('EnrollmentSeeder: 受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        if ($fixedStudent !== null) {
            $this->enrollFixedStudent($fixedStudent, $publishedCertifications);
        }

        $this->enrollDemoStudents($demoStudents, $publishedCertifications);
    }

    /**
     * 固定 student に published 資格 2 件を learning で登録(うち 1 件に達成済 / 未達成の目標を 2 件添える)。
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Certification>  $publishedCerts
     */
    private function enrollFixedStudent(User $student, $publishedCerts): void
    {
        $targets = $publishedCerts->take(2);

        foreach ($targets as $index => $certification) {
            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $student->id,
                    'certification_id' => $certification->id,
                ],
                [
                    'status' => EnrollmentStatus::Learning->value,
                    'current_term' => TermType::BasicLearning->value,
                    'exam_date' => now()->addMonths(2 + $index)->toDateString(),
                    'passed_at' => null,
                ],
            );

            EnrollmentStatusLog::firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'to_status' => EnrollmentStatus::Learning->value],
                [
                    'from_status' => null,
                    'changed_by_user_id' => $student->id,
                    'changed_at' => now()->subDays(30 - $index * 10),
                    'changed_reason' => '新規登録',
                ],
            );

            if ($index === 0 && $enrollment->goals()->doesntExist()) {
                EnrollmentGoal::factory()->for($enrollment)->achieved()->create([
                    'title' => '参考書 1 冊を読破する',
                    'achieved_at' => now()->subDays(5),
                ]);
                EnrollmentGoal::factory()->for($enrollment)->create([
                    'title' => '過去問 3 年分を解く',
                    'target_date' => now()->addDays(30)->toDateString(),
                ]);
            }
        }
    }

    /**
     * demo 受講生に対し各 status を網羅した受講登録を投入する(admin の status フィルタ・状態遷移ボタンの実機確認用)。
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $demoStudents
     * @param  \Illuminate\Database\Eloquent\Collection<int, Certification>  $publishedCerts
     */
    private function enrollDemoStudents($demoStudents, $publishedCerts): void
    {
        $patterns = [
            ['state' => 'learning', 'examDays' => 60],
            ['state' => 'learning', 'examDays' => 14, 'mockPractice' => true],
            ['state' => 'passed', 'examDays' => -10],
            ['state' => 'failed', 'examDays' => -3],
            ['state' => 'learning', 'examDays' => null],
        ];

        foreach ($demoStudents as $i => $student) {
            $pattern = $patterns[$i % count($patterns)];
            $certification = $publishedCerts->get($i % $publishedCerts->count());
            if ($certification === null) {
                continue;
            }

            $factory = Enrollment::factory()->for($student)->for($certification);
            $factory = match ($pattern['state']) {
                'learning' => $factory->learning(),
                'passed' => $factory->passed(),
                'failed' => $factory->failed(),
            };
            if (! empty($pattern['mockPractice'])) {
                $factory = $factory->mockPractice();
            }
            if ($pattern['examDays'] === null) {
                $factory = $factory->withoutExamDate();
            }

            $enrollment = $factory->create([
                'exam_date' => $pattern['examDays'] === null
                    ? null
                    : now()->addDays($pattern['examDays'])->toDateString(),
                'passed_at' => $pattern['state'] === 'passed' ? now()->subDays(7) : null,
            ]);

            $this->seedStatusLogs($enrollment, $pattern['state'], $student);
        }
    }

    private function seedStatusLogs(Enrollment $enrollment, string $finalState, User $student): void
    {
        EnrollmentStatusLog::factory()->for($enrollment)->create([
            'from_status' => null,
            'to_status' => EnrollmentStatus::Learning->value,
            'changed_by_user_id' => $student->id,
            'changed_at' => $enrollment->created_at,
            'changed_reason' => '新規登録',
        ]);

        if ($finalState === 'passed') {
            EnrollmentStatusLog::factory()->for($enrollment)->create([
                'from_status' => EnrollmentStatus::Learning->value,
                'to_status' => EnrollmentStatus::Passed->value,
                'changed_by_user_id' => $student->id,
                'changed_at' => $enrollment->passed_at ?? now(),
                'changed_reason' => '受講生による修了証受領',
            ]);
        }

        if ($finalState === 'failed') {
            EnrollmentStatusLog::factory()->for($enrollment)->create([
                'from_status' => EnrollmentStatus::Learning->value,
                'to_status' => EnrollmentStatus::Failed->value,
                'changed_by_user_id' => null,
                'changed_at' => now()->subDay(),
                'changed_reason' => '試験日超過による自動失敗',
            ]);
        }
    }
}
