<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuiz;

use App\Enums\ContentStatus;
use App\Models\Section;
use App\Models\User;

/**
 * Section エントリ画面のデータをまとめる Action。
 *
 * Section 配下の公開済 SectionQuestion を order 昇順で取得し、各問題の選択肢・カテゴリ・
 * 受講生本人の SectionQuestionAttempt を eager load する。
 */
final class ShowAction
{
    public function __invoke(Section $section, User $student): Section
    {
        return $section->load([
            'chapter.part.certification',
            'questions' => fn ($q) => $q
                ->where('status', ContentStatus::Published->value)
                ->orderBy('order')
                ->orderBy('id'),
            'questions.options' => fn ($q) => $q->orderBy('order'),
            'questions.category',
            'questions.sectionQuestionAttempts' => fn ($q) => $q->where('user_id', $student->id),
        ]);
    }
}
