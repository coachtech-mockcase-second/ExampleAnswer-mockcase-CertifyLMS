<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Exceptions\Content\ContentReorderInvalidException;
use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の並び替えユースケース。Part 配下の全 Chapter ID 網羅性と重複なしを検証してから一括 UPDATE する。
 */
final class ReorderAction
{
    /**
     * @param string[] $orderedIds
     *
     * @throws ContentReorderInvalidException
     */
    public function __invoke(Part $part, array $orderedIds): void
    {
        $existing = $part->chapters()->pluck('id')->all();

        if (count(array_diff($orderedIds, $existing)) > 0
            || count(array_diff($existing, $orderedIds)) > 0
            || count($orderedIds) !== count(array_unique($orderedIds))
        ) {
            throw new ContentReorderInvalidException;
        }

        DB::transaction(function () use ($part, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                Chapter::where('id', $id)
                    ->where('part_id', $part->id)
                    ->update(['order' => $idx + 1]);
            }
        });
    }
}
