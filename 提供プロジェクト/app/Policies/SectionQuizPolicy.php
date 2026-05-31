<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Section;
use App\Models\User;

/**
 * 受講生が Section 紐づき問題演習画面にアクセスできるかを判定する Policy。
 *
 * 教材閲覧系の SectionViewPolicy とは別 Gate(`quiz.section.view`)で登録する。
 * 判定: 本人 Student + 該当資格を learning または passed で受講中 + cascade visibility(Section / Chapter / Part すべて Published)。
 */
class SectionQuizPolicy
{
    public function view(User $auth, Section $section): bool
    {
        if ($auth->role !== UserRole::Student) {
            return false;
        }

        $section->loadMissing('chapter.part');
        $chapter = $section->chapter;
        $part = $chapter?->part;

        if ($chapter === null || $part === null) {
            return false;
        }

        if ($section->status !== ContentStatus::Published
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
