<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(Chapter $chapter, User $actor, array $validated): Section
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $maxOrder = $chapter->sections()->lockForUpdate()->max('order') ?? 0;

            return $chapter->sections()->create([
                ...$validated,
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
