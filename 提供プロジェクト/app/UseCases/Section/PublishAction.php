<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の公開ユースケース。Draft → Published の遷移のみ許可する。
 */
final class PublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Section $section): Section
    {
        if ($section->status !== ContentStatus::Draft) {
            throw ContentInvalidTransitionException::forSection(
                $section->status,
                ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($section) {
            $section->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $section->fresh();
        });
    }
}
