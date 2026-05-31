<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestionAnswer;

use App\Enums\AnswerSource;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\QuizAnswering\EnrollmentInactiveForAnswerException;
use App\Exceptions\QuizAnswering\SectionQuestionOptionMismatchException;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Http\Controllers\SectionQuestionAnswerController;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\SectionQuestionOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 1 件の SectionQuestion 解答を永続化するユースケース。
 *
 * Section 経路 / 苦手分野ドリル経路の両方から呼ばれる。トランザクション内で
 * SectionQuestionAnswer の INSERT と SectionQuestionAttempt の UPSERT を原子的に同期し、
 * 結果画面表示用の正答情報も含めた AnswerResult を返す。
 *
 * @see SectionQuestionAnswerController::store()
 */
final class StoreAction
{
    /**
     * @throws SectionQuestionUnavailableForAnswerException
     * @throws EnrollmentInactiveForAnswerException
     * @throws SectionQuestionOptionMismatchException
     */
    public function __invoke(
        User $user,
        SectionQuestion $question,
        SectionQuestionOption $option,
        AnswerSource $source,
    ): AnswerResult {
        $this->assertQuestionAvailable($question);
        $this->assertEnrollmentActive($user, $question);
        $this->assertOptionBelongsToQuestion($option, $question);

        return DB::transaction(function () use ($user, $question, $option, $source) {
            $isCorrect = $option->is_correct;

            $answer = SectionQuestionAnswer::create([
                'user_id' => $user->id,
                'section_question_id' => $question->id,
                'selected_option_id' => $option->id,
                'selected_option_body' => $option->body,
                'is_correct' => $isCorrect,
                'source' => $source,
                'answered_at' => now(),
            ]);

            // 同一ユーザー × 同一問題の Attempt 行を排他取得し、原子的に集計値を増分する。
            // ダブルクリック等の同時送信で 2 行 INSERT されて UNIQUE 制約違反になるのを避ける。
            $attempt = SectionQuestionAttempt::query()
                ->where('user_id', $user->id)
                ->where('section_question_id', $question->id)
                ->lockForUpdate()
                ->first();

            if ($attempt === null) {
                $attempt = SectionQuestionAttempt::create([
                    'user_id' => $user->id,
                    'section_question_id' => $question->id,
                    'attempt_count' => 1,
                    'correct_count' => $isCorrect ? 1 : 0,
                    'last_is_correct' => $isCorrect,
                    'last_answered_at' => now(),
                ]);
            } else {
                $attempt->fill([
                    'attempt_count' => $attempt->attempt_count + 1,
                    'correct_count' => $attempt->correct_count + ($isCorrect ? 1 : 0),
                    'last_is_correct' => $isCorrect,
                    'last_answered_at' => now(),
                ])->save();
            }

            $correctOption = $question->options()->where('is_correct', true)->first();

            return new AnswerResult(
                answer: $answer,
                attempt: $attempt->refresh(),
                correctOptionId: $correctOption?->id,
                correctOptionBody: $correctOption?->body,
                explanation: $question->explanation,
            );
        });
    }

    /**
     * @throws SectionQuestionUnavailableForAnswerException
     */
    private function assertQuestionAvailable(SectionQuestion $question): void
    {
        $question->loadMissing('section.chapter.part');
        $section = $question->section;
        $chapter = $section?->chapter;
        $part = $chapter?->part;

        if ($section === null || $chapter === null || $part === null) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        if ($question->status !== ContentStatus::Published
            || $section->status !== ContentStatus::Published
            || $chapter->status !== ContentStatus::Published
            || $part->status !== ContentStatus::Published) {
            throw new SectionQuestionUnavailableForAnswerException;
        }
    }

    /**
     * @throws EnrollmentInactiveForAnswerException
     */
    private function assertEnrollmentActive(User $user, SectionQuestion $question): void
    {
        $certificationId = $question->section?->chapter?->part?->certification_id;
        if ($certificationId === null) {
            throw new EnrollmentInactiveForAnswerException;
        }

        $exists = $user->enrollments()
            ->where('certification_id', $certificationId)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->exists();

        if (! $exists) {
            throw new EnrollmentInactiveForAnswerException;
        }
    }

    /**
     * @throws SectionQuestionOptionMismatchException
     */
    private function assertOptionBelongsToQuestion(SectionQuestionOption $option, SectionQuestion $question): void
    {
        if ($option->section_question_id !== $question->id) {
            throw new SectionQuestionOptionMismatchException;
        }
    }
}
