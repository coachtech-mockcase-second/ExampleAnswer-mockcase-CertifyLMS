<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\MockExamSessionStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamQuestionOption;
use App\Models\MockExamSession;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 模試 Feature の開発用シーダー。
 *
 * **設計思想(状態網羅 + 固定アカウント)**:
 *
 * 1. **MockExam マスタ網羅**: 各公開資格に対して、公開模試 × 3(第1回 / 第2回 / 第3回) + 下書き × 1 を生成する。
 *    - 公開模試は問題数 6 問(分野横断、合格点 60%)
 *    - 下書き模試は問題ゼロ(削除可 / 問題追加 UI の動作確認用)
 *
 * 2. **MockExamQuestion + Option**: 各公開模試に 6 問、各問に 4 つの選択肢(正答 1 件)。
 *    既存の QuestionCategory(ContentSeeder で投入済)から循環使用し、ヒートマップが描画可能な分野分散を保つ。
 *
 * 3. **固定 student のセッション状態網羅 + 合格可能性バンド網羅**: `student@certify-lms.test` の 4 件の learning
 *    Enrollment に、ダッシュボードの合格可能性バンドが資格ごとに異なって並ぶようセッションを作り分ける:
 *      - 1 資格目: danger 帯(Graded 50% + 17%、直近平均 ≈ 33%)+ InProgress(再開バナー)+ Canceled(履歴フィルタ)
 *      - 2 資格目: safe 帯(全公開模試 Graded 合格 100%、修了証ボタン活性化シナリオ)
 *      - 3 資格目: warning 帯(公開模試 1 回のみ Graded 50%、残りは未受験で「未受験 → 受験開始」導線も確認)
 *      - 4 資格目: データ不足帯(graded セッションなし、全模試未受験)
 *
 * 4. **demo student**: 残りの in_progress 受講生にもランダムに Graded セッションを散らし、
 *    admin / coach 画面の受験セッション一覧 + ヒートマップ集計動作を確認可能にする。
 *
 * 5. **CompletionEligibilityService 連携**: 固定 student の 1 つの資格について、全公開模試(3 件) すべてに合格セッションを
 *    つけて「修了証を受け取る」ボタン活性化シナリオを再現する。
 *
 * 依存順序: UserSeeder → CertificationSeeder → EnrollmentSeeder → ContentSeeder → 本 Seeder。
 */
final class MockExamSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();
        if ($admin === null) {
            $this->command?->warn('MockExamSeeder: admin ユーザーが見つかりません。先に UserSeeder を実行してください。');

            return;
        }

        $publishedCertifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('questionCategories')
            ->orderBy('created_at')
            ->get();

        if ($publishedCertifications->isEmpty()) {
            $this->command?->warn('MockExamSeeder: 公開済資格がありません。先に CertificationSeeder を実行してください。');

            return;
        }

        $mockExamsByCertification = [];
        foreach ($publishedCertifications as $certification) {
            $mockExamsByCertification[$certification->id] = $this->seedMockExamsForCertification($certification, $admin);
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($fixedStudent !== null) {
            $this->seedSessionsForFixedStudent($fixedStudent, $mockExamsByCertification);
        }

        $this->seedSessionsForDemoStudents($mockExamsByCertification);
    }

    /**
     * 1 資格分の MockExam(公開 × 3 + 下書き × 1) + 各問題 + 選択肢を投入する。
     *
     * @return Collection<int, MockExam>
     */
    private function seedMockExamsForCertification(Certification $certification, User $admin): Collection
    {
        $categories = $certification->questionCategories;
        if ($categories->isEmpty()) {
            // QuestionCategory が無い資格は問題を組成できないので、空の Collection を返してスキップする
            return collect();
        }

        $publishedConfigs = [
            ['title' => '第 1 回 本番形式模擬試験', 'order' => 1, 'passing_score' => 60],
            ['title' => '第 2 回 本番形式模擬試験', 'order' => 2, 'passing_score' => 60],
            ['title' => '第 3 回 本番形式模擬試験', 'order' => 3, 'passing_score' => 70],
        ];

        $created = collect();
        foreach ($publishedConfigs as $config) {
            $mockExam = MockExam::query()->create([
                'certification_id' => $certification->id,
                'title' => $certification->name.' '.$config['title'],
                'description' => '本番試験を想定した模擬試験です。時間制限なしで何度でも復習受験できます。',
                'order' => $config['order'],
                'passing_score' => $config['passing_score'],
                'is_published' => true,
                'published_at' => now()->subDays(30 - $config['order'] * 7),
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

            $this->seedQuestionsForMockExam($mockExam, $categories);
            $created->push($mockExam);
        }

        // 下書き模試(問題ゼロ)。削除可能 + 問題追加 UI の確認用
        MockExam::query()->create([
            'certification_id' => $certification->id,
            'title' => $certification->name.' 第 4 回 本番形式模擬試験',
            'description' => null,
            'order' => 4,
            'passing_score' => 60,
            'is_published' => false,
            'published_at' => null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        return $created;
    }

    /**
     * 模試に 6 問を投入する。分野は循環で散らし、各問に 4 つの選択肢(先頭が正答) を持たせる。
     *
     * @param Collection<int, QuestionCategory> $categories
     */
    private function seedQuestionsForMockExam(MockExam $mockExam, Collection $categories): void
    {
        for ($i = 0; $i < 6; $i++) {
            $category = $categories[$i % $categories->count()];

            $question = MockExamQuestion::query()->create([
                'mock_exam_id' => $mockExam->id,
                'category_id' => $category->id,
                'body' => sprintf(
                    '【%s】次の選択肢のうち、%s について最も正しい記述はどれか。',
                    $mockExam->title,
                    $category->name,
                ),
                'explanation' => $category->name.' に関する基本的な理解を問う問題です。',
                'order' => $i,
            ]);

            for ($j = 0; $j < 4; $j++) {
                MockExamQuestionOption::query()->create([
                    'mock_exam_question_id' => $question->id,
                    'body' => sprintf('選択肢 %s — %s に関する記述 %d', chr(65 + $j), $category->name, $j + 1),
                    'is_correct' => $j === 0,
                    'order' => $j,
                ]);
            }
        }
    }

    /**
     * 固定 student のセッション状態網羅シナリオ。
     *
     * 1 資格目: 第1回 Graded(合格 100%) / 第2回 Graded(不合格 33%) / 第3回 InProgress / 第4回 NotStarted
     * 2 資格目: 全公開模試で合格 — CompletionEligibilityService の「修了証を受け取る」ボタン活性化シナリオ
     *
     * @param array<string, Collection<int, MockExam>> $mockExamsByCertification
     */
    private function seedSessionsForFixedStudent(User $student, array $mockExamsByCertification): void
    {
        $enrollments = $student->enrollments()
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            $mockExams = $mockExamsByCertification[$enrollment->certification_id] ?? collect();
            if ($mockExams->isEmpty()) {
                continue;
            }

            // Enrollment ごとに合格可能性バンドを作り分ける(ダッシュボードで 4 帯すべてを 1 画面に並べる)。
            match ($index) {
                0 => $this->seedDangerBandForFixedStudent($enrollment, $student, $mockExams),
                1 => $this->seedAllPassedForFixedStudent($enrollment, $student, $mockExams),
                2 => $this->seedWarningBandForFixedStudent($enrollment, $student, $mockExams),
                default => null, // 4 件目以降: graded セッションを作らず未受験のまま → 「データ不足」帯 + 全模試未受験動線
            };
        }
    }

    /**
     * 1 資格目(合格可能性「要対策 danger」帯): 直近 graded の平均得点率を合格点(60%)の 70% 未満に抑える。
     * 併せて InProgress / Canceled も作り、履歴フィルタと「続きから再開」バナーを確認可能にする。
     *
     * @param Collection<int, MockExam> $mockExams
     */
    private function seedDangerBandForFixedStudent(Enrollment $enrollment, User $student, Collection $mockExams): void
    {
        // 第1回: Graded(50% = 6 問中 3 問正解)
        if ($first = $mockExams->get(0)) {
            $this->createGradedSession($first, $enrollment, $student, allCorrect: false, gradedDaysAgo: 10, correctCount: 3);
        }

        // 第2回: Graded(17% = 6 問中 1 問正解)→ 直近平均 ≈ 33% で danger 帯
        if ($second = $mockExams->get(1)) {
            $this->createGradedSession($second, $enrollment, $student, allCorrect: false, gradedDaysAgo: 3, correctCount: 1);
        }

        // 第3回: InProgress(2 問だけ解答済み、続きから再開バナーの確認用)
        if ($third = $mockExams->get(2)) {
            $this->createInProgressSession($third, $enrollment, $student, answeredCount: 2);
        }

        // Canceled セッションも 1 つ(履歴フィルタの「キャンセル済」確認用)
        if ($first) {
            $this->createCanceledSession($first, $enrollment, $student);
        }
    }

    /**
     * 2 資格目の固定 student シナリオ: 全公開模試に合格(修了申請ボタン活性化シナリオ)。
     *
     * @param Collection<int, MockExam> $mockExams
     */
    private function seedAllPassedForFixedStudent(Enrollment $enrollment, User $student, Collection $mockExams): void
    {
        foreach ($mockExams as $i => $mockExam) {
            $this->createGradedSession(
                $mockExam,
                $enrollment,
                $student,
                allCorrect: true,
                gradedDaysAgo: 15 - $i * 3,
            );
        }
    }

    /**
     * 3 資格目(合格可能性「注意 warning」帯): 公開模試を 1 回だけ受験し平均得点率を合格点の 70〜90% に収める。
     * 残りの公開模試は未受験のまま残し、「未受験 → 受験開始」導線も同時に確認できるようにする。
     *
     * @param Collection<int, MockExam> $mockExams
     */
    private function seedWarningBandForFixedStudent(Enrollment $enrollment, User $student, Collection $mockExams): void
    {
        // 第1回のみ受験: Graded(50% = 6 問中 3 問正解)→ 合格点 60% に対し warning 帯(42 ≤ 50 < 54)
        if ($first = $mockExams->get(0)) {
            $this->createGradedSession($first, $enrollment, $student, allCorrect: false, gradedDaysAgo: 6, correctCount: 3);
        }
    }

    /**
     * demo 受講生(固定 student 以外の in_progress) にランダムに Graded セッションを散らす。
     *
     * @param array<string, Collection<int, MockExam>> $mockExamsByCertification
     */
    private function seedSessionsForDemoStudents(array $mockExamsByCertification): void
    {
        $demoEnrollments = Enrollment::query()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->whereHas('user', fn ($q) => $q->where('email', '!=', 'student@certify-lms.test'))
            ->limit(8)
            ->with('user')
            ->get();

        foreach ($demoEnrollments as $index => $enrollment) {
            $mockExams = $mockExamsByCertification[$enrollment->certification_id] ?? collect();
            if ($mockExams->isEmpty() || $enrollment->user === null) {
                continue;
            }

            $allCorrect = $index % 3 !== 0;
            $correctCount = $allCorrect ? 6 : (1 + ($index % 4));

            $targetMockExam = $mockExams[$index % $mockExams->count()];
            $this->createGradedSession(
                $targetMockExam,
                $enrollment,
                $enrollment->user,
                allCorrect: $allCorrect,
                gradedDaysAgo: ($index % 7) + 1,
                correctCount: $correctCount,
            );
        }
    }

    private function createGradedSession(
        MockExam $mockExam,
        Enrollment $enrollment,
        User $student,
        bool $allCorrect,
        int $gradedDaysAgo,
        ?int $correctCount = null,
    ): MockExamSession {
        $questions = $mockExam->mockExamQuestions()->orderBy('order')->get();
        $totalQuestions = $questions->count();
        $correctCount = $correctCount ?? ($allCorrect ? $totalQuestions : (int) floor($totalQuestions * 0.4));
        $scorePercentage = $totalQuestions > 0
            ? round($correctCount / $totalQuestions * 100, 2)
            : 0.0;
        $pass = $scorePercentage >= (float) $mockExam->passing_score;

        $startedAt = now()->subDays($gradedDaysAgo)->subHours(1);
        $submittedAt = now()->subDays($gradedDaysAgo)->subMinutes(5);
        $gradedAt = now()->subDays($gradedDaysAgo);

        $session = MockExamSession::query()->create([
            'mock_exam_id' => $mockExam->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'status' => MockExamSessionStatus::Graded->value,
            'generated_question_ids' => $questions->pluck('id')->all(),
            'total_questions' => $totalQuestions,
            'passing_score_snapshot' => $mockExam->passing_score,
            'started_at' => $startedAt,
            'submitted_at' => $submittedAt,
            'graded_at' => $gradedAt,
            'total_correct' => $correctCount,
            'score_percentage' => $scorePercentage,
            'pass' => $pass,
        ]);

        foreach ($questions as $index => $question) {
            $isCorrect = $index < $correctCount;
            $option = $question->options
                ->firstWhere('is_correct', $isCorrect)
                ?? $question->options->first();

            if ($option === null) {
                continue;
            }

            MockExamAnswer::query()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $option->id,
                'selected_option_body' => $option->body,
                'is_correct' => $isCorrect,
                'answered_at' => $startedAt->copy()->addMinutes(2 + $index * 3),
            ]);
        }

        return $session;
    }

    private function createInProgressSession(MockExam $mockExam, Enrollment $enrollment, User $student, int $answeredCount): MockExamSession
    {
        $questions = $mockExam->mockExamQuestions()->orderBy('order')->get();
        $totalQuestions = $questions->count();

        $session = MockExamSession::query()->create([
            'mock_exam_id' => $mockExam->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'status' => MockExamSessionStatus::InProgress->value,
            'generated_question_ids' => $questions->pluck('id')->all(),
            'total_questions' => $totalQuestions,
            'passing_score_snapshot' => $mockExam->passing_score,
            'started_at' => now()->subHours(2),
        ]);

        foreach ($questions->take($answeredCount) as $index => $question) {
            $option = $question->options->firstWhere('is_correct', true)
                ?? $question->options->first();
            if ($option === null) {
                continue;
            }

            MockExamAnswer::query()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $option->id,
                'selected_option_body' => $option->body,
                'is_correct' => false,
                'answered_at' => now()->subHours(2)->addMinutes(5 + $index * 3),
            ]);
        }

        return $session;
    }

    private function createCanceledSession(MockExam $mockExam, Enrollment $enrollment, User $student): MockExamSession
    {
        $questions = $mockExam->mockExamQuestions()->orderBy('order')->get();

        return MockExamSession::query()->create([
            'mock_exam_id' => $mockExam->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'status' => MockExamSessionStatus::Canceled->value,
            'generated_question_ids' => $questions->pluck('id')->all(),
            'total_questions' => $questions->count(),
            'passing_score_snapshot' => $mockExam->passing_score,
            'canceled_at' => now()->subDays(20),
        ]);
    }
}
