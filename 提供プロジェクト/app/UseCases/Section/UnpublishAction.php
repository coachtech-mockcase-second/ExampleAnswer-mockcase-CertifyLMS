<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Section の下書きへの巻き戻しユースケース。Published → Draft の遷移のみ許可する。
 */
final class UnpublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(Section $section): Section
    {
        if ($section->status !== ContentStatus::Published) {
            throw ContentInvalidTransitionException::forSection(
                $section->status,
                ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($section) {
            $section->update(['status' => ContentStatus::Draft->value]);

            return $section->fresh();
        });
    }
}
