<?php

declare(strict_types=1);

namespace App\UseCases\MockExamQuestion;

use App\Models\MockExamQuestion;
use Illuminate\Support\Facades\DB;

/**
 * 模試問題を SoftDelete するユースケース。
 *
 * 過去の MockExamSession は `generated_question_ids` スナップショットを持つため、
 * SoftDelete された問題でも `withTrashed()` で参照できる(採点ロジックが整合性を保つ)。
 */
final class DestroyAction
{
    public function __invoke(MockExamQuestion $question): void
    {
        DB::transaction(fn () => $question->delete());
    }
}
