<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishAction
{
    public function __invoke(Part $part, User $actor): Part
    {
        if ($part->status !== ContentStatus::Published) {
            throw new ContentInvalidTransitionException(
                entity: 'Part',
                from: $part->status,
                to: ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($part) {
            $part->update(['status' => ContentStatus::Draft->value]);

            return $part->fresh();
        });
    }
}
