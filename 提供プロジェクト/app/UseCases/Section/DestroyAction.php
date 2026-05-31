<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の SoftDelete ユースケース。Draft 状態のみ削除可、Published は削除拒否。
 */
final class DestroyAction
{
    /**
     * @throws ContentNotDeletableException
     */
    public function __invoke(Section $section): void
    {
        if ($section->status !== ContentStatus::Draft) {
            throw ContentNotDeletableException::forSection();
        }

        DB::transaction(fn () => $section->delete());
    }
}
