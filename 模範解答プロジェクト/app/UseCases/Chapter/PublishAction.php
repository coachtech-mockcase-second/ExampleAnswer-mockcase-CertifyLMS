<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の公開ユースケース。Draft → Published の遷移のみ許可する。
 */
final class PublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Chapter $chapter): Chapter
    {
        if ($chapter->status !== ContentStatus::Draft) {
            throw ContentInvalidTransitionException::forChapter(
                $chapter->status,
                ContentStatus::Published,
            );
        }

        return DB::transaction(function () use ($chapter) {
            $chapter->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $chapter->fresh();
        });
    }
}
