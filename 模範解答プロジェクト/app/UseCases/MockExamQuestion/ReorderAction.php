<?php

declare(strict_types=1);

namespace App\UseCases\MockExamQuestion;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use Illuminate\Support\Facades\DB;

/**
 * 同一 MockExam 内の問題の並び順(`order`) を一括更新するユースケース。
 *
 * 親 MockExam に属さない MockExamQuestion ID が混在しても、`where mock_exam_id` で安全に絞り込む。
 */
final class ReorderAction
{
    /**
     * @param array<int, array{id: string, order: int}> $items
     */
    public function __invoke(MockExam $mockExam, array $items): void
    {
        DB::transaction(function () use ($mockExam, $items) {
            foreach ($items as $item) {
                MockExamQuestion::query()
                    ->where('id', $item['id'])
                    ->where('mock_exam_id', $mockExam->id)
                    ->update(['order' => $item['order']]);
            }
        });
    }
}
