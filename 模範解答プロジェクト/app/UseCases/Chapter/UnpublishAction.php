<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishAction
{
    public function __invoke(Chapter $chapter, User $actor): Chapter
    {
        if ($chapter->status !== ContentStatus::Published) {
            throw new ContentInvalidTransitionException(
                entity: 'Chapter',
                from: $chapter->status,
                to: ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($chapter) {
            $chapter->update(['status' => ContentStatus::Draft->value]);

            return $chapter->fresh();
        });
    }
}
