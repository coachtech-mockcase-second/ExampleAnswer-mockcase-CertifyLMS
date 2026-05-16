<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Exceptions\Content\ContentReorderInvalidException;
use App\Models\Chapter;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderAction
{
    /**
     * @param string[] $orderedIds
     */
    public function __invoke(Chapter $chapter, User $actor, array $orderedIds): void
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
