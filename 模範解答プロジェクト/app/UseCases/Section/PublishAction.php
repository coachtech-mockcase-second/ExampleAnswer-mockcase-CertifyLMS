<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Section $section, User $actor): Section
    {
        if ($section->status !== ContentStatus::Draft) {
            throw new ContentInvalidTransitionException(
                entity: 'Section',
                from: $section->status,
                to: ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($section) {
            $section->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $section->fresh();
        });
    }
}
