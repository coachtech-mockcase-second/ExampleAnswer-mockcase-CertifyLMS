<?php

declare(strict_types=1);

namespace App\UseCases\MockExamAnswer;

use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamOptionMismatchException;
use App\Exceptions\MockExam\MockExamQuestionNotInSessionException;
use App\Exceptions\MockExam\MockExamSessionNotInProgressException;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestionOption;
use App\Models\MockExamSession;
use Illuminate\Support\Facades\DB;

/**
 * 受験中の個別問題への解答を逐次保存(UPSERT) するユースケース。
 *
 * 3 段ガード:
 *   1. session.status === InProgress(lockForUpdate)
 *   2. mock_exam_question_id ∈ session.generated_question_ids
 *   3. selected_option_id ∈ question.options
 *
 * is_correct は採点時(GradeAction) に確定するため、本 Action では確定せず常に false で UPSERT する。
 * 既存解答が存在する場合は selected_option_id / selected_option_body / answered_at を UPDATE する。
 */
final class UpdateAction
{
    /**
     * @param  array{mock_exam_question_id: string, selected_option_id: string}  $validated
     *
     * @throws MockExamSessionNotInProgressException
     * @throws MockExamQuestionNotInSessionException
     * @throws MockExamOptionMismatchException
     */
    public function __invoke(MockExamSession $session, array $validated): MockExamAnswer
    {
        return DB::transaction(function () use ($session, $validated) {
            $session = MockExamSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== MockExamSessionStatus::InProgress) {
                throw new MockExamSessionNotInProgressException;
            }

            $generated = $session->generated_question_ids ?? [];
            if (! in_array($validated['mock_exam_question_id'], $generated, true)) {
                throw new MockExamQuestionNotInSessionException;
            }

            $option = MockExamQuestionOption::query()
                ->where('id', $validated['selected_option_id'])
                ->where('mock_exam_question_id', $validated['mock_exam_question_id'])
                ->first();

            if ($option === null) {
                throw new MockExamOptionMismatchException;
            }

            return MockExamAnswer::updateOrCreate(
                [
                    'mock_exam_session_id' => $session->id,
                    'mock_exam_question_id' => $validated['mock_exam_question_id'],
                ],
                [
                    'selected_option_id' => $option->id,
                    'selected_option_body' => $option->body,
                    'is_correct' => false,
                    'answered_at' => now(),
                ],
            );
        });
    }
}
