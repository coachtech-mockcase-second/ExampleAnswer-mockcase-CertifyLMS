<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;

/**
 * SectionQuestion(Section 紐づき演習問題)の認可ポリシー。
 *
 * - admin: 全資格配下の SectionQuestion を CRUD 可
 * - coach: 担当資格(certification_coach_assignments)配下の SectionQuestion のみ CRUD 可
 * - student: 自分の受講登録(enrollments)資格内の Published SectionQuestion のみ閲覧可
 * 親 Certification への到達は SectionQuestion → Section → Chapter → Part → Certification と辿る。
 */
class SectionQuestionPolicy
{
    public function viewAny(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function view(User $auth, SectionQuestion $question): bool
    {
        $certification = $question->section->chapter->part->certification;

        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role === UserRole::Coach) {
            return false;
        }

        if ($question->status !== ContentStatus::Published) {
            return false;
        }

        return $auth->enrollments()
            ->where('certification_id', $certification->id)
            ->exists();
    }

    public function create(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function update(User $auth, SectionQuestion $question): bool
    {
        return $this->canManage($auth, $question->section->chapter->part->certification);
    }

    public function delete(User $auth, SectionQuestion $question): bool
    {
        return $this->canManage($auth, $question->section->chapter->part->certification);
    }

    public function publish(User $auth, SectionQuestion $question): bool
    {
        return $this->canManage($auth, $question->section->chapter->part->certification);
    }

    public function unpublish(User $auth, SectionQuestion $question): bool
    {
        return $this->canManage($auth, $question->section->chapter->part->certification);
    }

    private function canManage(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => false,
            default => false,
        };
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
