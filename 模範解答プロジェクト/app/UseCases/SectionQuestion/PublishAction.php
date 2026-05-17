<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Exceptions\Content\QuestionNotPublishableException;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Section 紐づき問題の公開ユースケース。Draft → Published の遷移のみ許可する。
 *
 * 公開には選択肢が 2 件以上、かつ is_correct=true がちょうど 1 件存在する必要がある(整合性ガード)。
 */
final class PublishAction
{
    /**
     * @throws ContentInvalidTransitionException
     * @throws QuestionNotPublishableException
     */
    public function __invoke(SectionQuestion $question): SectionQuestion
    {
        if ($question->status !== ContentStatus::Draft) {
            throw ContentInvalidTransitionException::forSectionQuestion(
                $question->status,
                ContentStatus::Published,
            );
        }

        $options = $question->options()->get();
        if ($options->count() < 2 || $options->where('is_correct', true)->count() !== 1) {
            throw new QuestionNotPublishableException;
        }

        return DB::transaction(function () use ($question) {
            $question->update([
                'status' => ContentStatus::Published->value,
                'published_at' => now(),
            ]);

            return $question->fresh(['options', 'category']);
        });
    }
}
