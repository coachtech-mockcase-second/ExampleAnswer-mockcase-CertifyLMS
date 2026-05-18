<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestionOption;
use App\Models\MockExamSession;

/**
 * 受験セッションを採点する内部ユースケース。
 *
 * 必ず SubmitAction 内の `DB::transaction()` から呼ばれる前提で、自前のトランザクションは持たない。
 * 採点ロジック: 各 MockExamAnswer の selected_option_id を引いて、対応する MockExamQuestionOption の is_correct で is_correct を確定する。
 * SoftDelete された Option は `withTrashed()` で参照(セッション開始後にマスタが変わっても採点は安定)。
 */
final class GradeAction
{
    public function __invoke(MockExamSession $session): void
    {
        $answers = MockExamAnswer::query()
            ->where('mock_exam_session_id', $session->id)
            ->get();

        $optionIds = $answers
            ->pluck('selected_option_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $options = MockExamQuestionOption::withTrashed()
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        $totalCorrect = 0;
        foreach ($answers as $answer) {
            $isCorrect = false;
            if ($answer->selected_option_id !== null && $options->has($answer->selected_option_id)) {
                $isCorrect = (bool) $options[$answer->selected_option_id]->is_correct;
            }

            $answer->update(['is_correct' => $isCorrect]);

            if ($isCorrect) {
                $totalCorrect++;
            }
        }

        $totalQuestions = $session->total_questions;
        $scorePercentage = $totalQuestions > 0
            ? round($totalCorrect / $totalQuestions * 100, 2)
            : 0.0;
        $pass = $scorePercentage >= (float) $session->passing_score_snapshot;

        $session->update([
            'status' => MockExamSessionStatus::Graded->value,
            'graded_at' => now(),
            'total_correct' => $totalCorrect,
            'score_percentage' => $scorePercentage,
            'pass' => $pass,
        ]);
    }
}
