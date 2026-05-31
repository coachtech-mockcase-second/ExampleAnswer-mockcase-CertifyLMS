<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の新規作成ユースケース。親 Part 配下の MAX(order)+1 を採番し、status=Draft 固定で INSERT する。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: ?string} $validated
     */
    public function __invoke(Part $part, array $validated): Chapter
    {
        return DB::transaction(function () use ($part, $validated) {
            $maxOrder = $part->chapters()->lockForUpdate()->max('order') ?? 0;

            return $part->chapters()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
