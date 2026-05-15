<?php

namespace App\UseCases\Question;

use App\Models\Question;

class ShowAction
{
    public function __invoke(Question $question): Question
    {
        return $question->load([
            'certification',
            'section.chapter.part',
            'category',
            'options' => fn ($q) => $q->ordered(),
        ]);
    }
}
