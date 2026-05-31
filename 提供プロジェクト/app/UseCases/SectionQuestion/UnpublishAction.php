<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Section 紐づき問題の下書きへの巻き戻しユースケース。Published → Draft の遷移のみ許可する。
 */
final class UnpublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     */
    public function __invoke(SectionQuestion $question): SectionQuestion
    {
        if ($question->status !== ContentStatus::Published) {
            throw ContentInvalidTransitionException::forSectionQuestion(
                $question->status,
                ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($question) {
            $question->update(['status' => ContentStatus::Draft->value]);

            return $question->fresh(['options', 'category']);
        });
    }
}
