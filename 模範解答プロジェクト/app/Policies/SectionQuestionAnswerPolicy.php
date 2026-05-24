<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\User;

/**
 * 受講生の SectionQuestionAnswer(個別解答ログ)に対する認可ポリシー。
 *
 * - view: 本人のみ閲覧可
 * - create: 本人(Student) + ステータス受講中 + 対象資格を learning または passed で受講中 +
 *   cascade visibility(SectionQuestion / Section / Chapter / Part がすべて Published)
 */
class SectionQuestionAnswerPolicy
{
    public function view(User $auth, SectionQuestionAnswer $answer): bool
    {
        return $auth->id === $answer->user_id;
    }

    public function create(User $auth, SectionQuestion $question): bool
    {
        if ($auth->role !== UserRole::Student) {
            return false;
        }

        if ($auth->status !== UserStatus::InProgress) {
            return false;
        }

        $question->loadMissing('section.chapter.part');
        $section = $question->section;
        $chapter = $section?->chapter;
        $part = $chapter?->part;

        if ($section === null || $chapter === null || $part === null) {
            return false;
        }

        if ($question->status !== ContentStatus::Published
            || $section->status !== ContentStatus::Published
            || $chapter->status !== ContentStatus::Published
            || $part->status !== ContentStatus::Published) {
            return false;
        }

        return $auth->enrollments()
            ->where('certification_id', $part->certification_id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->exists();
    }
}
