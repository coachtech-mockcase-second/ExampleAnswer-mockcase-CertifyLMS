<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamSessionNotCancelableException;
use App\Models\MockExamSession;
use App\Services\TermJudgementService;
use Illuminate\Support\Facades\DB;

/**
 * NotStarted の受験セッションをキャンセル(Canceled に遷移) するユースケース。
 *
 * InProgress 以降の状態からはキャンセル不可。
 * 遷移後は TermJudgementService で current_term を再判定する(他に進行中 mock がなければ basic_learning に戻る)。
 */
final class DestroyAction
{
    public function __construct(
        private readonly TermJudgementService $termJudgement,
    ) {}

    /**
     * @throws MockExamSessionNotCancelableException
     */
    public function __invoke(MockExamSession $session): MockExamSession
    {
        return DB::transaction(function () use ($session) {
            $session = MockExamSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== MockExamSessionStatus::NotStarted) {
                throw new MockExamSessionNotCancelableException;
            }

            $session->update([
                'status' => MockExamSessionStatus::Canceled->value,
                'canceled_at' => now(),
            ]);

            $this->termJudgement->recalculate($session->enrollment);

            return $session->refresh();
        });
    }
}
