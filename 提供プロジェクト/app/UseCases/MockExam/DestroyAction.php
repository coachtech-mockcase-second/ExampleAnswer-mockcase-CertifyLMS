<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamInUseException;
use App\Models\MockExam;
use Illuminate\Support\Facades\DB;

/**
 * 模試マスタを SoftDelete するユースケース。
 *
 * 削除条件: 公開停止済(`is_published = false`) かつ、進行中(NotStarted / InProgress / Submitted) のセッションが残っていない。
 * 違反時は `MockExamInUseException`(409)。
 */
final class DestroyAction
{
    /**
     * @throws MockExamInUseException
     */
    public function __invoke(MockExam $mockExam): void
    {
        if ($mockExam->is_published) {
            throw MockExamInUseException::forPublished();
        }

        $activeSessionExists = $mockExam->sessions()
            ->whereIn('status', [
                MockExamSessionStatus::NotStarted->value,
                MockExamSessionStatus::InProgress->value,
                MockExamSessionStatus::Submitted->value,
            ])
            ->exists();

        if ($activeSessionExists) {
            throw MockExamInUseException::forActiveSessions();
        }

        DB::transaction(fn () => $mockExam->delete());
    }
}
