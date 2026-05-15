<?php

namespace App\UseCases\Question;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentInvalidTransitionException;
use App\Exceptions\Content\QuestionNotPublishableException;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Question $question, User $actor): Question
    {
        if ($question->status !== ContentStatus::Draft) {
            throw new ContentInvalidTransitionException(
                entity: 'Question',
                from: $question->status,
                to: ContentStatus::Published,
            );
        }

        $options = $question->options()->get();
        if ($options->count() < 2 || $options->where('is_correct', true)->count() !== 1) {
            throw new QuestionNotPublishableException();
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
