<?php

namespace App\UseCases\Question;

use App\Exceptions\Content\QuestionInUseException;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DestroyAction
{
    public function __invoke(Question $question): void
    {
        if (Schema::hasTable('mock_exam_questions')) {
            $inUse = DB::table('mock_exam_questions')
                ->where('question_id', $question->id)
                ->exists();
            if ($inUse) {
                throw new QuestionInUseException();
            }
        }

        DB::transaction(fn () => $question->delete());
    }
}
