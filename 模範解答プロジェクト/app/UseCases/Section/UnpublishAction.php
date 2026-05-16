<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishAction
{
    public function __invoke(Section $section, User $actor): Section
    {
        if ($section->status !== ContentStatus::Published) {
            throw new ContentInvalidTransitionException(
                entity: 'Section',
                from: $section->status,
                to: ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($section) {
            $section->update(['status' => ContentStatus::Draft->value]);

            return $section->fresh();
        });
    }
}
