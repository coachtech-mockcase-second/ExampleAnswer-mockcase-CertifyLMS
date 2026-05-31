<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TermType;
use App\Models\Enrollment;
use App\Models\MockExamSession;

/**
 * 受講生 × 資格の学習ターム(基礎ターム / 実践ターム) を判定する Service。
 *
 * MockExamSession.status が in_progress / submitted / graded のいずれかであるレコードが 1 件でも存在すれば
 * 実践ターム(mock_practice)、そうでなければ基礎ターム(basic_learning)。
 *
 * MockExamSession の状態変化を伴う各 Action(StartAction / SubmitAction / CancelAction 等) がトランザクション内で
 * 呼ぶ契約。現状の current_term と新判定が一致する場合は UPDATE しない(不要な書き込みを避ける)。
 */
final class TermJudgementService
{
    /**
     * @return TermType 確定後の current_term。呼出側で再表示等に利用する。
     */
    public function recalculate(Enrollment $enrollment): TermType
    {
        $hasActiveMock = MockExamSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['in_progress', 'submitted', 'graded'])
            ->exists();

        $newTerm = $hasActiveMock ? TermType::MockPractice : TermType::BasicLearning;

        if ($enrollment->current_term !== $newTerm) {
            $enrollment->update(['current_term' => $newTerm->value]);
        }

        return $newTerm;
    }
}
