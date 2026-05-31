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
use App\Models\EnrollmentGoal;
use App\Models\EnrollmentNote;
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

            $this->seedNotesForEnrollment($enrollment, $admin);
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

            // demo student の Enrollment にも 1/2 の確率でコーチメモを添える(担当 coach がいる場合のみ)。
            // 認可境界(coach 担当外なら Note 投稿不可)を反映するため、担当 coach から拾って投稿する。
            if ($i % 2 === 0) {
                $this->seedNotesForEnrollment($enrollment, $admin);
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
     * 当該 Enrollment の資格に割り当てられた coach 集合 + admin から、コーチメモを 1-3 件 INSERT する。
     *
     * 担当 coach が複数いる資格(例: TOEIC は coach1 / coach2 両者)では、両者と admin が並ぶ
     * 「他コーチが書いたノートを admin は越境編集可、自分以外の coach は 403」シナリオが視覚的に確認できる状態を作る。
     */
    private function seedNotesForEnrollment(Enrollment $enrollment, ?User $admin): void
    {
        $enrollment->loadMissing('certification.coaches');
        $coaches = $enrollment->certification?->coaches ?? collect();

        if ($coaches->isEmpty() && $admin === null) {
            return;
        }

        $observationTemplates = [
            '今週の面談で本人の苦手分野を聞き取った。アルゴリズム系の演習量が不足している様子。',
            '過去問の取り組みは順調。次回面談までに模試 1 回受験を目標に設定。',
            '本人のモチベーションがやや低下気味。週次の小目標で達成感を作る方針で次の面談を組む。',
            '直近の演習で正答率が改善。実践タームへの移行タイミングを次回面談で検討する。',
            '質問掲示板での投稿頻度が上がっており、自走力が育っている様子。',
        ];

        // 担当 coach 全員 1 件ずつ
        foreach ($coaches as $coachIndex => $coach) {
            EnrollmentNote::factory()->for($enrollment)->create([
                'coach_user_id' => $coach->id,
                'body' => $observationTemplates[$coachIndex % count($observationTemplates)],
                'created_at' => now()->subDays(7 + $coachIndex * 2),
                'updated_at' => now()->subDays(7 + $coachIndex * 2),
            ]);
        }

        // admin 越境ノート(他コーチが書いたノートを admin が編集 / 削除できるシナリオを demo 化)
        if ($admin !== null) {
            EnrollmentNote::factory()->for($enrollment)->create([
                'coach_user_id' => $admin->id,
                'body' => '運用補足: コーチ間で観察記録を共有。次月の面談ペース調整を検討。',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
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
