<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の SoftDelete ユースケース。Draft 状態のみ削除可、Published は削除拒否。
 */
final class DestroyAction
{
    /**
     * @throws ContentNotDeletableException
     */
    public function __invoke(Chapter $chapter): void
    {
        if ($chapter->status !== ContentStatus::Draft) {
            throw ContentNotDeletableException::forChapter();
        }

        DB::transaction(fn () => $chapter->delete());
    }
}
