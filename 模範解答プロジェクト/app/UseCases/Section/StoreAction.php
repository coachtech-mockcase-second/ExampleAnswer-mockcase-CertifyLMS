<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の新規作成ユースケース。親 Chapter 配下の MAX(order)+1 を採番し、status=Draft 固定で INSERT する。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: ?string, body: string} $validated
     */
    public function __invoke(Chapter $chapter, array $validated): Section
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $maxOrder = $chapter->sections()->lockForUpdate()->max('order') ?? 0;

            return $chapter->sections()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'body' => $validated['body'],
                'status' => ContentStatus::Draft->value,
                'order' => $maxOrder + 1,
                'published_at' => null,
            ]);
        });
    }
}
