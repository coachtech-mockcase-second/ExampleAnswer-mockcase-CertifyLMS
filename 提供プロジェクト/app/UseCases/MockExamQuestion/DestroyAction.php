<?php

declare(strict_types=1);

namespace App\UseCases\MockExamQuestion;

use App\Models\MockExamQuestion;
use Illuminate\Support\Facades\DB;

/**
 * 模試問題を削除するユースケース。
 *
 * 過去の MockExamAnswer から参照されている場合は外部キー制約(restrictOnDelete)で削除が阻止される
 * ため、採点済セッションの整合性は DB レベルで保護される。
 */
final class DestroyAction
{
    public function __invoke(MockExamQuestion $question): void
    {
        DB::transaction(fn () => $question->delete());
    }
}
