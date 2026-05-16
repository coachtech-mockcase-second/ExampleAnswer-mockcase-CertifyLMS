<?php

declare(strict_types=1);

namespace App\UseCases\Question;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishAction
{
    public function __invoke(Question $question, User $actor): Question
    {
        if ($question->status !== ContentStatus::Published) {
            throw new ContentInvalidTransitionException(
                entity: 'Question',
                from: $question->status,
                to: ContentStatus::Draft,
            );
        }

        return DB::transaction(function () use ($question) {
            $question->update(['status' => ContentStatus::Draft->value]);

            return $question->fresh(['options', 'category']);
        });
    }
}
