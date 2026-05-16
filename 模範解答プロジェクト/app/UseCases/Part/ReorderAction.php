<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Exceptions\Content\ContentReorderInvalidException;
use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderAction
{
    /**
     * @param string[] $orderedIds Part ID を表示順に並べた配列
     */
    public function __invoke(Certification $certification, User $actor, array $orderedIds): void
    {
        $existing = $certification->parts()->pluck('id')->all();

        if (count(array_diff($orderedIds, $existing)) > 0
            || count(array_diff($existing, $orderedIds)) > 0
            || count($orderedIds) !== count(array_unique($orderedIds))
        ) {
            throw new ContentReorderInvalidException;
        }

        DB::transaction(function () use ($certification, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                Part::where('id', $id)
                    ->where('certification_id', $certification->id)
                    ->update(['order' => $idx + 1]);
            }
        });
    }
}
