<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * 演習問題の削除ユースケース。
 */
final class DestroyAction
{
    public function __invoke(SectionQuestion $question): void
    {
        DB::transaction(fn () => $question->delete());
    }
}
