<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Enums\EnrollmentStatus;
use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamHasNoQuestionsException;
use App\Exceptions\MockExam\MockExamSessionAlreadyInProgressException;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 受講生が公開模試を新規受験するためのセッションを作成するユースケース。
 *
 * 検証順:
 *   1. 受講生が当該資格に learning / passed のいずれかで登録済か(なければ 403 AccessDenied)
 *   2. 同一受講登録 × 同一模試で進行中(NotStarted / InProgress / Submitted) セッションがないか
 *   3. 模試に問題が 1 件以上組成されているか
 *
 * セッションは `status = NotStarted` で INSERT し、`generated_question_ids` に MockExamQuestion.id の配列スナップショットを保存する。
 */
final class StoreAction
{
    /**
     * @throws AccessDeniedHttpException
     * @throws MockExamSessionAlreadyInProgressException
     * @throws MockExamHasNoQuestionsException
     */
    public function __invoke(User $student, MockExam $mockExam): MockExamSession
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('certification_id', $mockExam->certification_id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->first();

        if ($enrollment === null) {
            throw new AccessDeniedHttpException('この模試を受験できる受講登録がありません。');
        }

        $activeSessionExists = MockExamSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('mock_exam_id', $mockExam->id)
            ->whereIn('status', [
                MockExamSessionStatus::NotStarted->value,
                MockExamSessionStatus::InProgress->value,
                MockExamSessionStatus::Submitted->value,
            ])
            ->exists();

        if ($activeSessionExists) {
            throw new MockExamSessionAlreadyInProgressException;
        }

        $questionIds = $mockExam->mockExamQuestions()
            ->orderBy('order')
            ->pluck('id')
            ->all();

        if (count($questionIds) === 0) {
            throw new MockExamHasNoQuestionsException;
        }

        return DB::transaction(fn () => MockExamSession::create([
            'mock_exam_id' => $mockExam->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $student->id,
            'status' => MockExamSessionStatus::NotStarted->value,
            'generated_question_ids' => $questionIds,
            'total_questions' => count($questionIds),
            'passing_score_snapshot' => $mockExam->passing_score,
        ]));
    }
}
