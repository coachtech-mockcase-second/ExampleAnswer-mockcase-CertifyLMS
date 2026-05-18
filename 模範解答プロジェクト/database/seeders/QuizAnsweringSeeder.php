<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnswerSource;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Section 紐づき問題演習の解答ログ + Attempt サマリ シーダー。
 *
 * 設計思想:
 *
 * 1. **状態網羅**: 全問正解の Section / 半分正解 / 全問誤答 / 未解答 を Enrollment 単位で散らす。
 *    section エントリ / 結果画面 / 履歴 / 問題別サマリ / 苦手分野ドリル のおすすめバッジ動作を実機確認できる。
 *
 * 2. **固定アカウント**: `student@certify-lms.test` の learning Enrollment 配下に厚めの履歴を投入する。
 *    解答履歴一覧のページネーション / フィルタ / 問題別サマリの並び替えを 1 アカウントで一通り確認可能。
 *
 * 3. **source 混在**: section_quiz と weak_drill を 1:1 程度で混在させ、出題経路フィルタの動作確認用素材とする。
 *
 * 4. **試行回数バリエーション**: SectionQuestionAttempt の attempt_count を 1 / 2 / 3+ に散らし、
 *    「これまで N 回挑戦」の表示パターンを網羅する。
 *
 * 依存順序: UserSeeder → CertificationSeeder → EnrollmentSeeder → ContentSeeder → 本 Seeder。
 */
final class QuizAnsweringSeeder extends Seeder
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
        $enrollments = $student->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->get();

        foreach ($enrollments as $enrollment) {
            $questions = $this->publishedQuestionsFor($enrollment);
            if ($questions->isEmpty()) {
                continue;
            }

            // 固定アカウントは履歴をしっかり厚くする(20 件以上を散らす)
            $thirds = $questions->chunk(max(1, (int) ceil($questions->count() / 3)));
            $allCorrect = $thirds->get(0) ?? collect();
            $mixed = $thirds->get(1) ?? collect();
            $allWrong = $thirds->get(2) ?? collect();

            foreach ($allCorrect as $i => $question) {
                $this->seedAnswerAndAttempt(
                    $student,
                    $question,
                    attemptCount: 1,
                    correctCount: 1,
                    lastIsCorrect: true,
                    answeredAt: now()->subDays(3)->subMinutes($i),
                    source: AnswerSource::SectionQuiz,
                );
            }

            foreach ($mixed as $i => $question) {
                $isCorrect = $i % 2 === 0;
                $this->seedAnswerAndAttempt(
                    $student,
                    $question,
                    attemptCount: $isCorrect ? 2 : 3,
                    correctCount: $isCorrect ? 2 : 1,
                    lastIsCorrect: $isCorrect,
                    answeredAt: now()->subDays(1)->subMinutes($i * 5),
                    source: $i % 3 === 0 ? AnswerSource::WeakDrill : AnswerSource::SectionQuiz,
                );
            }

            foreach ($allWrong as $i => $question) {
                $this->seedAnswerAndAttempt(
                    $student,
                    $question,
                    attemptCount: 1,
                    correctCount: 0,
                    lastIsCorrect: false,
                    answeredAt: now()->subHours(2)->subMinutes($i * 3),
                    source: AnswerSource::WeakDrill,
                );
            }
        }
    }

    private function seedForLearningEnrollments(): void
    {
        $enrollments = Enrollment::query()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->whereDoesntHave('user', fn ($q) => $q->where('email', 'student@certify-lms.test'))
            ->limit(8)
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            $questions = $this->publishedQuestionsFor($enrollment);
            if ($questions->isEmpty()) {
                continue;
            }

            $ratio = match ($index % 4) {
                0 => 0.2,
                1 => 0.5,
                2 => 0.8,
                default => 1.0,
            };

            $targets = $questions->take((int) ceil($questions->count() * $ratio));

            foreach ($targets as $i => $question) {
                $isCorrect = ($i + $index) % 3 !== 0;
                $attemptCount = 1 + ($i % 3);
                $this->seedAnswerAndAttempt(
                    $enrollment->user,
                    $question,
                    attemptCount: $attemptCount,
                    correctCount: $isCorrect ? min($attemptCount, max(1, $attemptCount - ($i % 2))) : 0,
                    lastIsCorrect: $isCorrect,
                    answeredAt: now()->subDays($i % 5)->subHours($index),
                    source: $i % 2 === 0 ? AnswerSource::SectionQuiz : AnswerSource::WeakDrill,
                );
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, SectionQuestion>
     */
    private function publishedQuestionsFor(Enrollment $enrollment): \Illuminate\Support\Collection
    {
        return SectionQuestion::query()
            ->where('status', ContentStatus::Published->value)
            ->whereHas(
                'section.chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->with('options')
            ->orderBy('id')
            ->get();
    }

    private function seedAnswerAndAttempt(
        User $user,
        SectionQuestion $question,
        int $attemptCount,
        int $correctCount,
        bool $lastIsCorrect,
        Carbon $answeredAt,
        AnswerSource $source,
    ): void {
        $options = $question->options;
        if ($options->isEmpty()) {
            return;
        }

        $correctOption = $options->firstWhere('is_correct', true);
        $wrongOption = $options->firstWhere('is_correct', false);

        // 試行回数分の SectionQuestionAnswer を散らして INSERT する。
        // 直近の解答だけ lastIsCorrect に合わせ、それ以前は正答数に応じて埋める。
        $remainingCorrect = $correctCount;
        for ($i = 0; $i < $attemptCount; $i++) {
            $isLast = $i === $attemptCount - 1;
            $isCorrect = $isLast ? $lastIsCorrect : ($remainingCorrect > 0);
            if ($isCorrect && ! $isLast) {
                $remainingCorrect--;
            }
            $option = $isCorrect ? $correctOption : ($wrongOption ?? $correctOption);

            SectionQuestionAnswer::factory()
                ->forUser($user)
                ->forQuestion($question)
                ->forOption($option)
                ->source($source)
                ->state([
                    'is_correct' => $isCorrect,
                    'answered_at' => $answeredAt->copy()->subMinutes($attemptCount - 1 - $i),
                ])
                ->create();
        }

        SectionQuestionAttempt::factory()
            ->forUser($user)
            ->forQuestion($question)
            ->state([
                'attempt_count' => $attemptCount,
                'correct_count' => $correctCount,
                'last_is_correct' => $lastIsCorrect,
                'last_answered_at' => $answeredAt,
            ])
            ->create();
    }
}
