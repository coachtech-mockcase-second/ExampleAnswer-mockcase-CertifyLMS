<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(Part $part, User $actor, array $validated): Chapter
    {
        return DB::transaction(function () use ($part, $validated) {
            $maxOrder = $part->chapters()->lockForUpdate()->max('order') ?? 0;

            return $part->chapters()->create([
                ...$validated,
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
