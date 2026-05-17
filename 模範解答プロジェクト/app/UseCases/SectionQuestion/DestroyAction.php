<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * 演習問題の SoftDelete ユースケース。
 *
 * 演習解答履歴 (`\App\Models\SectionQuestionAttempt` / `\App\Models\SectionQuestionAnswer`) は `withTrashed()` で
 * SoftDelete 済 SectionQuestion を参照できるため、削除を阻害しない(履歴的関連付けは保持される)。
 */
final class DestroyAction
{
    public function __invoke(SectionQuestion $question): void
    {
        DB::transaction(fn () => $question->delete());
    }
}
