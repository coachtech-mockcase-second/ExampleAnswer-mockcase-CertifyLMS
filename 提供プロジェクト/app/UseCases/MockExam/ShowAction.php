<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Models\MockExam;

/**
 * 模試マスタ詳細を取得するユースケース。問題件数とセッション件数を併記する。
 */
final class ShowAction
{
    public function __invoke(MockExam $mockExam): MockExam
    {
        return $mockExam
            ->loadCount(['mockExamQuestions', 'sessions'])
            ->load(['certification', 'createdBy', 'updatedBy']);
    }
}
