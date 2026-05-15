<?php

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Chapter $chapter, User $actor): Chapter
    {
        if ($chapter->status !== ContentStatus::Draft) {
            throw new ContentInvalidTransitionException(
                entity: 'Chapter',
                from: $chapter->status,
                to: ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($chapter) {
            $chapter->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $chapter->fresh();
        });
    }
}
