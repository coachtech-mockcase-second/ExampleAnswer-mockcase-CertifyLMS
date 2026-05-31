<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の更新ユースケース。title / description / body(Markdown) を更新する(status は別 Action で遷移)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string, body: string} $validated Section/UpdateRequest::rules() で検証済
     */
    public function __invoke(Section $section, array $validated): Section
    {
        return DB::transaction(function () use ($section, $validated) {
            $section->update($validated);

            return $section->fresh();
        });
    }
}
