<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Chapter $chapter): void
    {
        if ($chapter->status !== ContentStatus::Draft) {
            throw new ContentNotDeletableException(entity: 'Chapter');
        }

        DB::transaction(fn () => $chapter->delete());
    }
}
