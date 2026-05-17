<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Models\Part;
use Illuminate\Support\Facades\DB;

/**
 * Part の更新ユースケース。title / description を更新する(status は別 Action で遷移)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string} $validated Part/UpdateRequest::rules() で検証済
     */
    public function __invoke(Part $part, array $validated): Part
    {
        return DB::transaction(function () use ($part, $validated) {
            $part->update($validated);

            return $part->fresh();
        });
    }
}
