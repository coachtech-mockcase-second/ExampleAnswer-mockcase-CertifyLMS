<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の SoftDelete ユースケース。Draft 状態のみ削除可、Published 状態は削除拒否(先に下書きへ戻す必要がある)。
 */
final class DestroyAction
{
    /**
     * @throws ContentNotDeletableException
     */
    public function __invoke(Part $part): void
    {
        if ($part->status !== ContentStatus::Draft) {
            throw ContentNotDeletableException::forPart();
        }

        DB::transaction(fn () => $part->delete());
    }
}
