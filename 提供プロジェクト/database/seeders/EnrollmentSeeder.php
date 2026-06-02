<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use App\Services\CertificatePdfService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 開発用 受講登録シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定アカウント、派生・運用系)**:
 *
 * 1. **固定アカウント**(deterministic): 動作確認・スクショ撮影で安定して参照できる「決まったユーザー」の受講登録を生成する。
 *    - `student@certify-lms.test` を CertificationSeeder 投入の published 資格 4 件に learning で登録(ダッシュボードの合格可能性バンド safe / warning / danger / データ不足 を 1 画面で網羅するため)
 *    - 1 件目に達成済 / 未達成の個人目標を 2 件追加(目標 CRUD・達成マーク UI の即時確認用)
 *    - coach@(`coach1`) / coach2@ / admin@ が固定 student の Enrollment にメモを残す(他コーチ越境拒否シナリオ用)
 *
 * 2. **状態網羅 demo データ**(Factory + state + count): 一覧 / フィルタ / 状態遷移ボタン / 認可境界が各 status で動くことを実機確認する。
 *    - learning(基礎ターム)/ learning(実践ターム)/ passed / failed / learning(試験日未設定) の 5 パターンを demo student に循環配分
 *    - passed Enrollment には Certificate(`certificates`)を INSERT し、PDF 実体も生成(修了済み → PDF DL の実機確認用)
 *    - 担当 coach が割り当てられている資格 Enrollment にはコーチメモを 1-2 件散らす(coach 動線の即時確認用)
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当 coach 割当含む)→ 本 Seeder。
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
        // UserSeeder で投入される in_progress 受講生 demo の件数(8 件)に合わせて取得。
        // 固定 student は別ハンドリングするので whereNot で除外し、残り 8 件を循環パターンに割当てる。
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

        $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();

        if ($fixedStudent !== null) {
            $this->enrollFixedStudent($fixedStudent, $publishedCertifications, $admin);
        }

        $this->enrollDemoStudents($demoStudents, $publishedCertifications, $admin);
    }

    /**
     * 固定 student に published 資格 4 件を learning で登録し、1 件目に個人目標 + 各件にコーチメモを添える。
     *
     * 4 件にするのは、ダッシュボードの合格可能性バンド(safe / warning / danger / データ不足)を 1 画面で
     * 網羅させるため(各 Enrollment の模試スコアは MockExamSeeder が帯ごとに作り分ける)。
     *
     * @param Collection<int, Certification> $publishedCerts
     */
    private function enrollFixedStudent(User $student, $publishedCerts, ?User $admin): void
    {
        $targets = $publishedCerts->take(4);

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
        }
    }

    /**
     * demo 受講生に対し各 status を網羅した受講登録を投入する(admin の status フィルタ・状態遷移ボタンの実機確認用)。
     *
     * @param Collection<int, User> $demoStudents
     * @param Collection<int, Certification> $publishedCerts
     */
    private function enrollDemoStudents($demoStudents, $publishedCerts, ?User $admin): void
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

            $passedAt = $pattern['state'] === 'passed' ? now()->subDays(7) : null;

            $enrollment = $factory->create([
                'exam_date' => $pattern['examDays'] === null
                    ? null
                    : now()->addDays($pattern['examDays'])->toDateString(),
                'passed_at' => $passedAt,
            ]);

            $this->seedStatusLogs($enrollment, $pattern['state'], $student);

            if ($pattern['state'] === 'passed') {
                $this->issueCertificate($enrollment, $passedAt);
            }
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


    /**
     * passed Enrollment に対し、修了証(`certificates`)行を INSERT し PDF 実体も生成する。
     *
     * 受講生 enrollments.show の「修了済み → PDF DL リンク」と修了証 DL の実機確認用。
     */
    private function issueCertificate(Enrollment $enrollment, ?Carbon $passedAt): void
    {
        if (Certificate::query()->where('enrollment_id', $enrollment->id)->exists()) {
            return;
        }

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create([
                'issued_at' => $passedAt ?? now(),
            ]);

        // 修了証 DL の実機確認用に PDF 実体まで生成する(発行フロー外での補完のため Service を直接呼ぶ)
        app(CertificatePdfService::class)->generate($certificate);
    }
}
