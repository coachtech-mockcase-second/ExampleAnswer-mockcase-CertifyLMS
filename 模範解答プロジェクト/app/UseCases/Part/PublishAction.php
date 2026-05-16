<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Part $part, User $actor): Part
    {
        if ($part->status !== ContentStatus::Draft) {
            throw new ContentInvalidTransitionException(
                entity: 'Part',
                from: $part->status,
                to: ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($part) {
            $part->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $part->fresh();
        });
    }
}
