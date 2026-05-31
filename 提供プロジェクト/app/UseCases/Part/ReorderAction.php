<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Exceptions\Content\ContentReorderInvalidException;
use App\Models\Certification;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の並び替えユースケース。
 *
 * 渡された順序 ID 配列が当該資格配下の全 Part を網羅し、重複なく対応することを検証してから一括 UPDATE する。
 */
final class ReorderAction
{
    /**
     * @param string[] $orderedIds Part ID を表示順に並べた配列
     *
     * @throws ContentReorderInvalidException
     */
    public function __invoke(Certification $certification, array $orderedIds): void
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
