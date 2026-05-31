<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Exceptions\Content\ContentReorderInvalidException;
use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の並び替えユースケース。Chapter 配下の全 Section ID 網羅性と重複なしを検証してから一括 UPDATE する。
 */
final class ReorderAction
{
    /**
     * @param string[] $orderedIds
     *
     * @throws ContentReorderInvalidException
     */
    public function __invoke(Chapter $chapter, array $orderedIds): void
    {
        $existing = $chapter->sections()->pluck('id')->all();

        if (count(array_diff($orderedIds, $existing)) > 0
            || count(array_diff($existing, $orderedIds)) > 0
            || count($orderedIds) !== count(array_unique($orderedIds))
        ) {
            throw new ContentReorderInvalidException;
        }

        DB::transaction(function () use ($chapter, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                Section::where('id', $id)
                    ->where('chapter_id', $chapter->id)
                    ->update(['order' => $idx + 1]);
            }
        });
    }
}
