<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の更新ユースケース。title / description を更新する(status は別 Action で遷移)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string} $validated Chapter/UpdateRequest::rules() で検証済
     */
    public function __invoke(Chapter $chapter, array $validated): Chapter
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $chapter->update($validated);

            return $chapter->fresh();
        });
    }
}
