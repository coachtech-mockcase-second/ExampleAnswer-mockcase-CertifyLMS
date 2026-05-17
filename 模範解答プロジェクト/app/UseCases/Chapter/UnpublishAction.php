<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の下書きへの巻き戻しユースケース。Published → Draft の遷移のみ許可する。
 */
final class UnpublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Chapter $chapter): Chapter
    {
        if ($chapter->status !== ContentStatus::Published) {
            throw ContentInvalidTransitionException::forChapter(
                $chapter->status,
                ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($chapter) {
            $chapter->update(['status' => ContentStatus::Draft->value]);

            return $chapter->fresh();
        });
    }
}
